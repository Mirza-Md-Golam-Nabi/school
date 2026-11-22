<?php

use App\Actions\BuildTeacherImportTemplateAction;
use App\Actions\ImportTeachersFromSpreadsheetAction;
use App\Enums\AddressType;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\UserType;
use App\Filament\Resources\TeacherProfiles\Pages\ListTeacherProfiles;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('teacher');
});

/**
 * @param  array<int, array<int, mixed>>  $rows  first row is the header
 */
function makeTeacherImportTestFile(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'teacher-import-test').'.xlsx';

    $writer = new Writer;
    $writer->openToFile($path);

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return $path;
}

it('builds a demo file whose header row lists every teacher import column with dropdowns on the enum columns', function () {
    $path = tempnam(sys_get_temp_dir(), 'teacher-import-read').'.xlsx';
    file_put_contents($path, app(BuildTeacherImportTemplateAction::class)->handle());

    $reader = new Reader;
    $reader->open($path);
    $sheets = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        $sheets[$sheet->getName()] = array_map(fn (Row $row): array => $row->toArray(), iterator_to_array($sheet->getRowIterator(), false));
    }

    $reader->close();

    $headers = array_map(fn (string $header): string => rtrim($header, '*'), $sheets['Teachers'][0]);

    expect($headers)->toBe(array_keys(ImportTeachersFromSpreadsheetAction::COLUMNS))
        ->and($sheets['Teachers'][0])->toContain('name*', 'email*', 'gender*', 'designation')
        ->and(collect($sheets['Instructions'])->firstWhere(0, 'email')[1])->toBe('Yes');

    $spreadsheet = IOFactory::load($path);
    $teachers = $spreadsheet->getSheetByName('Teachers');
    $genderColumn = Coordinate::stringFromColumnIndex(array_search('gender', $headers) + 1);
    $nameColumn = Coordinate::stringFromColumnIndex(array_search('name', $headers) + 1);

    expect($spreadsheet->getSheetByName(BuildTeacherImportTemplateAction::LISTS_SHEET)->getSheetState())->toBe(Worksheet::SHEETSTATE_HIDDEN)
        ->and($teachers->getCell("{$genderColumn}2")->getDataValidation()->getType())->toBe(DataValidation::TYPE_LIST)
        ->and($teachers->getCell("{$nameColumn}2")->getDataValidation()->getType())->toBe(DataValidation::TYPE_NONE);
});

it('imports every row of a filled-in demo file as a teacher with a login account', function () {
    $path = makeTeacherImportTestFile([
        ['name*', 'email*', 'gender*', 'designation', 'joining_date', 'blood_group', 'nationality', 'present_address', 'permanent_address'],
        [null, null, null, null, null, null, 'Bangladeshi', null, null],
        ['Abdul Karim', 'Karim@School.com', 'Male', 'Assistant Teacher', '01/01/2020', 'o+', null, 'Mirpur, Dhaka', 'Cumilla'],
        ['Fatema Akter', 'fatema@school.com', 'female', null, null, 'N/A', null, null, null],
    ]);

    $result = app(ImportTeachersFromSpreadsheetAction::class)->handle($path);

    expect($result)->toBe(['imported' => 2, 'errors' => []]);

    $karim = User::where('email', 'karim@school.com')->sole();
    $profile = $karim->teacherProfile;

    expect($karim->name)->toBe('Abdul Karim')
        ->and($karim->user_type)->toBe(UserType::Teacher)
        ->and($karim->hasRole('teacher'))->toBeTrue()
        ->and(Hash::check(ImportTeachersFromSpreadsheetAction::DEFAULT_PASSWORD, $karim->password))->toBeTrue()
        ->and($profile->gender)->toBe(Gender::Male)
        ->and($profile->designation)->toBe('Assistant Teacher')
        ->and($profile->joining_date->toDateString())->toBe('2020-01-01')
        ->and($profile->blood_group->value)->toBe('O+')
        ->and($profile->status)->toBe(EmploymentStatus::Active)
        ->and($profile->nationality)->toBe('Bangladeshi')
        ->and($profile->addresses()->where('type', AddressType::Present)->value('address'))->toBe('Mirpur, Dhaka')
        ->and($profile->addresses()->where('type', AddressType::Permanent)->value('address'))->toBe('Cumilla')
        ->and(User::where('email', 'fatema@school.com')->sole()->teacherProfile->blood_group)->toBeNull();
});

it('imports nothing and reports each bad teacher row when the file has errors', function () {
    User::factory()->create(['email' => 'taken@school.com']);

    $path = makeTeacherImportTestFile([
        ['name', 'email', 'gender', 'date_of_birth'],
        ['Valid Teacher', 'valid@school.com', 'male', null],
        [null, 'noname@school.com', 'male', null],
        ['Bad Email', 'not-an-email', 'boy', '1990-13-45'],
        ['Taken Email', 'TAKEN@school.com', 'female', null],
        ['Same Email', 'valid@school.com', 'female', null],
    ]);

    $result = app(ImportTeachersFromSpreadsheetAction::class)->handle($path);
    $errors = implode(' | ', $result['errors']);

    expect($result['imported'])->toBe(0)
        ->and(TeacherProfile::count())->toBe(0)
        ->and($errors)->toContain('Row 3: name')
        ->and($errors)->toContain('Row 4: email')
        ->and($errors)->toContain('Row 4: gender "boy"')
        ->and($errors)->toContain('Row 4: date_of_birth "1990-13-45"')
        ->and($errors)->toContain('Row 5: email taken@school.com')
        ->and($errors)->toContain('Row 6: email valid@school.com এই ফাইলের Row 2')
        ->and($errors)->not->toContain('Row 2:');
});

it('rejects a teacher file that is missing a required column', function () {
    $path = makeTeacherImportTestFile([
        ['name', 'gender'],
        ['No Email', 'male'],
    ]);

    $result = app(ImportTeachersFromSpreadsheetAction::class)->handle($path);

    expect($result['imported'])->toBe(0)
        ->and($result['errors'][0])->toContain('email')
        ->and(TeacherProfile::count())->toBe(0);
});

it('offers the demo file download and imports teachers from the upload action on the teacher list page', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $path = makeTeacherImportTestFile([
        ['name', 'email', 'gender'],
        ['Abdul Karim', 'karim@school.com', 'male'],
        ['Fatema Akter', 'fatema@school.com', 'female'],
    ]);

    Livewire::test(ListTeacherProfiles::class)
        ->callAction('downloadTeacherImportTemplate')
        ->assertFileDownloaded('teacher-import-demo.xlsx')
        ->callAction('importTeachers', [
            'file' => UploadedFile::fake()->createWithContent('teachers.xlsx', file_get_contents($path)),
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('2 জন teacher import হয়েছে');

    expect(TeacherProfile::count())->toBe(2);
});
