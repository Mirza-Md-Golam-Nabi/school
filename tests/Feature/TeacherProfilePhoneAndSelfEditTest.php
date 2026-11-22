<?php

use App\Actions\BuildTeacherImportTemplateAction;
use App\Actions\ImportTeachersFromSpreadsheetAction;
use App\Enums\AddressType;
use App\Enums\BloodGroup;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\UserType;
use App\Filament\Resources\TeacherProfiles\Pages\CreateTeacherProfile;
use App\Filament\Resources\TeacherProfiles\Pages\EditTeacherProfile;
use App\Filament\Teacher\Pages\Auth\EditProfile;
use App\Models\SchoolAccount;
use App\Models\TeacherProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('teacher');
});

it('saves the phone number when an admin creates and edits a teacher', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create()));

    Livewire::test(CreateTeacherProfile::class)
        ->fillForm([
            'email' => 'phone.teacher@example.com',
            'name' => 'Phone Teacher',
            'phone' => '01712345678',
            'password' => 'password',
            'status' => EmploymentStatus::Active->value,
            'gender' => Gender::Male->value,
            'nationality' => 'Bangladeshi',
            'present_address' => 'Dhaka',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'phone.teacher@example.com')->sole();

    expect($user->phone)->toBe('01712345678');

    Livewire::test(EditTeacherProfile::class, ['record' => $user->teacherProfile->getRouteKey()])
        ->assertSchemaStateSet(['phone' => '01712345678'])
        ->fillForm(['phone' => '01898765432', 'present_address' => 'Dhaka'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->phone)->toBe('01898765432');
});

it('keeps the phone number optional when creating a teacher', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create()));

    Livewire::test(CreateTeacherProfile::class)
        ->fillForm([
            'email' => 'no.phone@example.com',
            'name' => 'No Phone',
            'password' => 'password',
            'status' => EmploymentStatus::Active->value,
            'gender' => Gender::Female->value,
            'nationality' => 'Bangladeshi',
            'present_address' => 'Dhaka',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::where('email', 'no.phone@example.com')->sole()->phone)->toBeNull();
});

it('offers a text-formatted phone column in the demo file and imports the phone number', function () {
    $templatePath = tempnam(sys_get_temp_dir(), 'teacher-phone-template').'.xlsx';
    file_put_contents($templatePath, app(BuildTeacherImportTemplateAction::class)->handle());

    $sheet = IOFactory::load($templatePath)->getSheetByName('Teachers');
    $headers = array_map(fn (?string $header): string => rtrim((string) $header, '*'), $sheet->toArray()[0]);
    $phoneColumn = Coordinate::stringFromColumnIndex(array_search('phone', $headers, true) + 1);

    $nationalityColumn = Coordinate::stringFromColumnIndex(array_search('nationality', $headers, true) + 1);

    foreach (['phone', 'joining_date', 'date_of_birth'] as $textColumnName) {
        $textColumn = Coordinate::stringFromColumnIndex(array_search($textColumnName, $headers, true) + 1);
        $textColumnStyle = $sheet->getParent()->getCellXfByIndex($sheet->getColumnDimension($textColumn)->getXfIndex() ?? 0);

        expect($sheet->getStyle("{$textColumn}2")->getNumberFormat()->getFormatCode())->toBe(NumberFormat::FORMAT_TEXT)
            ->and($textColumnStyle->getNumberFormat()->getFormatCode())->toBe(NumberFormat::FORMAT_TEXT);
    }

    expect($sheet->getStyle("{$phoneColumn}2")->getNumberFormat()->getFormatCode())->toBe(NumberFormat::FORMAT_TEXT)
        ->and($sheet->getCell("{$nationalityColumn}2")->getValue())->toBe('Bangladeshi')
        ->and($sheet->getCell("{$nationalityColumn}3")->getValue())->toBeNull();

    $path = tempnam(sys_get_temp_dir(), 'teacher-phone-import').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['name*', 'email*', 'phone', 'gender*']));
    $writer->addRow(Row::fromValues(['Abdul Karim', 'karim@school.com', '01712345678', 'male']));
    $writer->addRow(Row::fromValues(['Fatema Akter', 'fatema@school.com', 1812345678, 'female']));
    $writer->addRow(Row::fromValues(['Rahim Uddin', 'rahim@school.com', null, 'male']));
    $writer->close();

    expect(app(ImportTeachersFromSpreadsheetAction::class)->handle($path))->toBe(['imported' => 3, 'errors' => []])
        ->and(User::where('email', 'karim@school.com')->value('phone'))->toBe('01712345678')
        ->and(User::where('email', 'fatema@school.com')->value('phone'))->toBe('01812345678')
        ->and(User::where('email', 'rahim@school.com')->value('phone'))->toBeNull();
});

it('lets a teacher update their full profile but not their email', function () {
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 0]);
    $user = User::factory()->create([
        'user_type' => UserType::Teacher,
        'is_active' => true,
        'email' => 'teacher@school.com',
    ]);
    $teacher = TeacherProfile::factory()->create([
        'user_id' => $user->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
        'default_school_account_id' => $account->id,
    ]);
    $teacher->addresses()->create(['type' => AddressType::Present, 'address' => 'Old Address', 'is_same' => true]);

    Filament::setCurrentPanel('teacher');
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->assertSchemaStateSet(['email' => 'teacher@school.com', 'present_address' => 'Old Address', 'same_address' => true])
        ->assertFormFieldDisabled('email')
        ->fillForm([
            'email' => 'hacked@school.com',
            'name' => 'New Name',
            'phone' => '01712345678',
            'designation' => 'Senior Teacher',
            'department' => 'Science',
            'qualification' => 'M.Sc',
            'joining_date' => '2020-01-01',
            'gender' => Gender::Female->value,
            'date_of_birth' => '1990-05-12',
            'blood_group' => BloodGroup::OPlus->value,
            'nationality' => 'Bangladeshi',
            'present_address' => 'Mirpur, Dhaka',
            'same_address' => false,
            'permanent_address' => 'Cumilla',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();
    $teacher->refresh();

    expect($user->email)->toBe('teacher@school.com')
        ->and($user->name)->toBe('New Name')
        ->and($user->phone)->toBe('01712345678')
        ->and($teacher->designation)->toBe('Senior Teacher')
        ->and($teacher->department)->toBe('Science')
        ->and($teacher->qualification)->toBe('M.Sc')
        ->and($teacher->joining_date->toDateString())->toBe('2020-01-01')
        ->and($teacher->gender)->toBe(Gender::Female)
        ->and($teacher->date_of_birth->toDateString())->toBe('1990-05-12')
        ->and($teacher->blood_group)->toBe(BloodGroup::OPlus)
        ->and($teacher->status)->toBe(EmploymentStatus::Active)
        ->and($teacher->default_school_account_id)->toBe($account->id)
        ->and($teacher->addresses()->where('type', AddressType::Present)->value('address'))->toBe('Mirpur, Dhaka')
        ->and($teacher->addresses()->where('type', AddressType::Permanent)->value('address'))->toBe('Cumilla');
});
