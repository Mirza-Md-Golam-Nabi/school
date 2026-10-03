<?php

use App\Actions\BuildClassStudentIdCardsPdfAction;
use App\Enums\AddressType;
use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Enums\StudentIdCardField;
use App\Enums\StudentIdCardValidity;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Pages\StudentIdCards;
use App\Models\Classes;
use App\Models\Group;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\IdCardPhotoRenderer;
use App\Support\StudentIdCardLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function makeIdCardTestStudent(int $classId, int $rollNo, StudentStatus $status = StudentStatus::Active, array $attributes = []): StudentProfile
{
    $user = User::factory()->create([
        'user_type' => UserType::Student,
        'is_active' => true,
        'name' => "Student Roll {$rollNo}",
    ]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => $rollNo,
        'current_class_id' => $classId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => $status,
        ...$attributes,
    ]);
}

it('builds an id card pdf for every active student in the class', function () {
    $class = Classes::create(['name' => 'ID Card Class', 'order' => 1]);

    makeIdCardTestStudent($class->id, 2);
    makeIdCardTestStudent($class->id, 1);

    // A non-active student must be excluded.
    makeIdCardTestStudent($class->id, 3, StudentStatus::Graduated);

    $pdf = app(BuildClassStudentIdCardsPdfAction::class)->handle($class);

    expect($pdf)->toStartWith('%PDF');
});

it('fits four students on a page and starts a new page for the fifth', function () {
    $class = Classes::create(['name' => 'ID Card Paging Class', 'order' => 1]);

    foreach (range(1, 4) as $rollNo) {
        makeIdCardTestStudent($class->id, $rollNo);
    }

    $onePagePdf = app(BuildClassStudentIdCardsPdfAction::class)->handle($class);

    makeIdCardTestStudent($class->id, 5);

    $twoPagePdf = app(BuildClassStudentIdCardsPdfAction::class)->handle($class);

    expect(preg_match_all('/\/Type\s*\/Page\b/', $onePagePdf))->toBe(1)
        ->and(preg_match_all('/\/Type\s*\/Page\b/', $twoPagePdf))->toBe(2);
});

it('renders a fully filled-in card with photo, logo, group, section and guardian details', function () {
    Storage::fake('public');

    $photo = UploadedFile::fake()->image('photo.jpg', 300, 450)->store('student-profiles/photos', 'public');
    SchoolSetting::set('school_name', 'আদর্শ উচ্চ বিদ্যালয়');
    SchoolSetting::set('school_logo', UploadedFile::fake()->image('logo.png', 120, 120)->store('school-settings', 'public'));

    $class = Classes::create(['name' => 'ID Card Full Class', 'order' => 1]);
    $group = Group::create(['name' => 'Science']);
    $section = Section::create(['class_id' => $class->id, 'name' => 'A']);

    $student = makeIdCardTestStudent($class->id, 1, StudentStatus::Active, [
        'current_group_id' => $group->id,
        'current_section_id' => $section->id,
        'registration_no' => 123456,
        'blood_group' => BloodGroup::BPlus,
        'father_name' => 'মোঃ আব্দুল করিম',
        'mother_name' => 'Rokeya Begum',
        'guardian_phone' => '01812345678',
        'date_of_birth' => '2011-03-14',
        'photo' => $photo,
    ]);
    $student->addresses()->create(['type' => AddressType::Present, 'address' => 'Rupdia, Jashore']);

    $pdf = app(BuildClassStudentIdCardsPdfAction::class)->handle($class);

    expect($pdf)->toStartWith('%PDF');
});

it('excludes students belonging to a different class', function () {
    $class = Classes::create(['name' => 'ID Card Class A', 'order' => 1]);
    $otherClass = Classes::create(['name' => 'ID Card Class B', 'order' => 2]);

    makeIdCardTestStudent($otherClass->id, 1);

    expect(fn () => app(BuildClassStudentIdCardsPdfAction::class)->handle($class))
        ->toThrow(NotFoundHttpException::class);
});

it('frames the avatar as a transparent round png and falls back to a placeholder', function () {
    Storage::fake('public');

    $avatar = UploadedFile::fake()->image('avatar.jpg', 300, 450)->store('avatars', 'public');
    $renderer = app(IdCardPhotoRenderer::class);

    $framed = imagecreatefromstring(base64_decode(Str::after($renderer->render($avatar), 'base64,')));
    $corner = imagecolorsforindex($framed, imagecolorat($framed, 0, 0));
    $centre = imagecolorsforindex($framed, imagecolorat($framed, 150, 150));

    expect(imagesx($framed))->toBe(300)
        ->and($corner['alpha'])->toBe(127)
        ->and($centre['alpha'])->toBe(0)
        ->and($renderer->render(null))->toStartWith('data:image/png;base64,')
        ->and($renderer->render('avatars/missing.jpg'))->toBe($renderer->render(null));
});

it('streams the id cards pdf as a download through the http route', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'ID Card Route Class', 'order' => 1]);

    makeIdCardTestStudent($class->id, 1);

    $response = $this->actingAs($admin)->get(route('student-id-cards.class.download', ['class' => $class->id]));

    $response->assertStatus(Response::HTTP_OK);
    $response->assertHeader('Content-Type', 'application/pdf');
    $response->assertHeader('Content-Disposition', "attachment; filename=\"student-id-cards-{$class->id}.pdf\"");
});

it('streams the id cards pdf inline through the view route so it opens in the browser', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'ID Card View Route Class', 'order' => 1]);

    makeIdCardTestStudent($class->id, 1);

    $response = $this->actingAs($admin)->get(route('student-id-cards.class.view', ['class' => $class->id]));

    $response->assertStatus(Response::HTTP_OK);
    $response->assertHeader('Content-Type', 'application/pdf');
    $response->assertHeader('Content-Disposition', "inline; filename=\"student-id-cards-{$class->id}.pdf\"");
});

it('renders the student id card page listing active classes with view and download links', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'ID Card Page Class', 'order' => 1, 'is_active' => true]);
    $emptyClass = Classes::create(['name' => 'ID Card Empty Page Class', 'order' => 2, 'is_active' => true]);

    makeIdCardTestStudent($class->id, 1);

    $this->actingAs($admin)->get(StudentIdCards::getUrl())
        ->assertOk()
        ->assertSee($class->name)
        ->assertSee($emptyClass->name)
        ->assertSee(route('student-id-cards.class.view', ['class' => $class->id]), false)
        ->assertSee(route('student-id-cards.class.download', ['class' => $class->id]), false)
        ->assertDontSee(route('student-id-cards.class.view', ['class' => $emptyClass->id]), false);
});

/**
 * Render the card sheet for one student using whatever layout is currently saved.
 */
function renderIdCardSheetFor(StudentProfile $student): string
{
    return view('documents.student-id-card-sheet', [
        'school' => ['name' => 'Test School', 'address' => '', 'logo' => null, 'signature' => null],
        'frontBackground' => 'front.svg',
        'backBackground' => 'back.svg',
        'cardWidth' => 54.0,
        'cardHeight' => 85.6,
        'frontFields' => StudentIdCardLayout::frontFields(),
        'backFields' => StudentIdCardLayout::backFields(),
        'validity' => StudentIdCardLayout::validity(),
        'issueDate' => StudentIdCardLayout::issueDate(),
        'cards' => collect([[
            'student' => $student->load(['user', 'class', 'group', 'section', 'addresses']),
            'photo' => 'photo.png',
            'x' => 10.0,
            'y' => 10.0,
        ]]),
    ])->render();
}

it('defaults to a permanent card layout with no yearly-changing fields', function () {
    expect(StudentIdCardLayout::frontFields())->toBe([StudentIdCardField::IdNo, StudentIdCardField::BloodGroup, StudentIdCardField::DateOfBirth])
        ->and(StudentIdCardLayout::backFields())->toBe([StudentIdCardField::FatherName, StudentIdCardField::MotherName, StudentIdCardField::EmergencyContact, StudentIdCardField::Address])
        ->and(StudentIdCardLayout::validity())->toBe(StudentIdCardValidity::IssueDate)
        ->and(collect([...StudentIdCardLayout::frontFields(), ...StudentIdCardLayout::backFields()])->contains(fn (StudentIdCardField $field) => $field->changesYearly()))->toBeFalse();
});

it('prints only the default permanent fields on the card until the layout is changed', function () {
    $class = Classes::create(['name' => 'Layout Default Class', 'order' => 1]);
    $student = makeIdCardTestStudent($class->id, 7, StudentStatus::Active, [
        'blood_group' => BloodGroup::BPlus,
        'father_name' => 'Abdul Karim',
        'mother_name' => 'Rokeya Begum',
        'admission_date' => '2022-01-05',
    ]);

    $html = renderIdCardSheetFor($student);

    expect($html)
        ->toContain('ID NO', 'BLOOD', 'Abdul Karim', 'Rokeya Begum', 'ISSUE DATE', mb_strtoupper(now()->format('d M Y')), 'ADMISSION')
        ->not->toContain('ROLL NO', 'Layout Default Class', 'VALID TILL', 'SESSION');
});

it('prints the fields saved for each side and leaves out the ones the student has no value for', function () {
    $class = Classes::create(['name' => 'Layout Custom Class', 'order' => 1]);
    $student = makeIdCardTestStudent($class->id, 7, StudentStatus::Active, [
        'father_name' => 'Abdul Karim',
        'mother_name' => 'Rokeya Begum',
    ]);

    StudentIdCardLayout::save(
        [StudentIdCardField::ClassName->value, StudentIdCardField::RollNo->value, StudentIdCardField::BloodGroup->value],
        [StudentIdCardField::MotherName->value, StudentIdCardField::Session->value],
        StudentIdCardValidity::SessionEnd,
    );

    $html = renderIdCardSheetFor($student);

    expect($html)
        ->toContain('ROLL NO', 'Layout Custom Class', 'Rokeya Begum', 'SESSION', 'VALID TILL')
        ->not->toContain('ID NO', 'Abdul Karim', 'BLOOD', 'ISSUE DATE');
});

it('keeps the id card number stable when the student is promoted to a new session', function () {
    $class = Classes::create(['name' => 'ID Number Class', 'order' => 1]);
    $student = makeIdCardTestStudent($class->id, 1, StudentStatus::Active, ['admission_date' => '2022-01-05']);

    $numberBeforePromotion = $student->idCardNumber();

    $student->update(['session_year' => $student->session_year + 1]);

    expect($numberBeforePromotion)->toBe(sprintf('2022%04d', $student->id))
        ->and($student->fresh()->idCardNumber())->toBe($numberBeforePromotion);
});

it('saves the chosen card fields from the card fields modal', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    Livewire::test(StudentIdCards::class)
        ->mountAction('configureCardFields')
        ->set('mountedActions.0.data.front_fields', ['id_no', 'class', 'roll_no'])
        ->set('mountedActions.0.data.back_fields', ['father_name'])
        ->set('mountedActions.0.data.validity', 'session_end')
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect(StudentIdCardLayout::frontFields())->toBe([StudentIdCardField::IdNo, StudentIdCardField::ClassName, StudentIdCardField::RollNo])
        ->and(StudentIdCardLayout::backFields())->toBe([StudentIdCardField::FatherName])
        ->and(StudentIdCardLayout::validity())->toBe(StudentIdCardValidity::SessionEnd);
});

it('prints the fields in the serial arranged in the card fields modal', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    Livewire::test(StudentIdCards::class)
        ->mountAction('configureCardFields')
        ->set('mountedActions.0.data.front_order', [
            'a' => ['field' => 'date_of_birth'],
            'b' => ['field' => 'id_no'],
            'c' => ['field' => 'blood_group'],
        ])
        ->set('mountedActions.0.data.back_order', [
            'a' => ['field' => 'mother_name'],
            'b' => ['field' => 'father_name'],
            'c' => ['field' => 'address'],
            'd' => ['field' => 'emergency_contact'],
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(StudentIdCardLayout::frontFields())->toBe([StudentIdCardField::DateOfBirth, StudentIdCardField::IdNo, StudentIdCardField::BloodGroup])
        ->and(StudentIdCardLayout::backFields())->toBe([StudentIdCardField::MotherName, StudentIdCardField::FatherName, StudentIdCardField::Address, StudentIdCardField::EmergencyContact]);

    $class = Classes::create(['name' => 'Serial Class', 'order' => 1]);
    $html = renderIdCardSheetFor(makeIdCardTestStudent($class->id, 1, StudentStatus::Active, [
        'blood_group' => BloodGroup::BPlus,
        'date_of_birth' => '2011-03-14',
        'father_name' => 'Abdul Karim',
        'mother_name' => 'Rokeya Begum',
    ]));

    expect(strpos($html, 'BIRTH DATE'))->toBeLessThan(strpos($html, 'ID NO'))
        ->and(strpos($html, 'ID NO'))->toBeLessThan(strpos($html, '>BLOOD<'))
        ->and(strpos($html, 'Rokeya Begum'))->toBeLessThan(strpos($html, 'Abdul Karim'));
});

it('keeps the arranged serial when a field is ticked or unticked', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    Livewire::test(StudentIdCards::class)
        ->mountAction('configureCardFields')
        ->set('mountedActions.0.data.front_order', [
            'a' => ['field' => 'date_of_birth'],
            'b' => ['field' => 'id_no'],
            'c' => ['field' => 'blood_group'],
        ])
        ->set('mountedActions.0.data.front_fields', ['date_of_birth', 'blood_group', 'class'])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(StudentIdCardLayout::frontFields())->toBe([StudentIdCardField::DateOfBirth, StudentIdCardField::BloodGroup, StudentIdCardField::ClassName]);
});

it('prefills the card fields modal with the default layout', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    Livewire::test(StudentIdCards::class)
        ->mountAction('configureCardFields')
        ->assertActionDataSet([
            'front_fields' => ['id_no', 'blood_group', 'date_of_birth'],
            'back_fields' => ['father_name', 'mother_name', 'emergency_contact', 'address'],
            'validity' => 'issue_date',
            'issue_date' => today()->toDateString(),
        ]);
});

it('prints the issue date chosen in the card fields modal in the 03 OCT 2026 pattern', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    Livewire::test(StudentIdCards::class)
        ->mountAction('configureCardFields')
        ->set('mountedActions.0.data.issue_date', '2026-01-05')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $class = Classes::create(['name' => 'Issue Date Class', 'order' => 1]);

    expect(StudentIdCardLayout::issueDate()->toDateString())->toBe('2026-01-05')
        ->and(renderIdCardSheetFor(makeIdCardTestStudent($class->id, 1)))->toContain('ISSUE DATE', '05 JAN 2026');
});

it('requires an issue date only when the issue date option is chosen', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    Livewire::test(StudentIdCards::class)
        ->mountAction('configureCardFields')
        ->set('mountedActions.0.data.issue_date', null)
        ->callMountedAction()
        ->assertHasActionErrors(['issue_date' => 'required']);

    Livewire::test(StudentIdCards::class)
        ->mountAction('configureCardFields')
        ->set('mountedActions.0.data.validity', 'session_end')
        ->set('mountedActions.0.data.issue_date', null)
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(StudentIdCardLayout::validity())->toBe(StudentIdCardValidity::SessionEnd);
});

it('rejects more fields on one side than the card has room for', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    Livewire::test(StudentIdCards::class)
        ->callAction('configureCardFields', data: [
            'front_fields' => ['id_no', 'class', 'roll_no', 'section', 'group', 'session', 'blood_group'],
            'back_fields' => ['father_name'],
            'validity' => 'issue_date',
        ])
        ->assertHasActionErrors(['front_fields']);

    expect(StudentIdCardLayout::frontFields())->toBe(StudentIdCardField::defaultFront());
});
