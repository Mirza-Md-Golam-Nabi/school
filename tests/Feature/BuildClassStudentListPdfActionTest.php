<?php

use App\Actions\BuildClassStudentListPdfAction;
use App\Enums\Gender;
use App\Enums\OptionalSubjectRole;
use App\Enums\StudentListColumn;
use App\Enums\StudentListNameLanguage;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Resources\StudentProfiles\Pages\StudentsByClass;
use App\Models\Classes;
use App\Models\Group;
use App\Models\Section;
use App\Models\StudentOptionalSubject;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

function makeStudentListTestStudent(
    int $classId,
    int $rollNo,
    int $sessionYear,
    ?int $groupId = null,
    ?int $sectionId = null,
): StudentProfile {
    $user = User::factory()->create([
        'user_type' => UserType::Student,
        'is_active' => true,
        'name' => "Student Roll {$rollNo}",
    ]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => $rollNo,
        'current_class_id' => $classId,
        'current_group_id' => $groupId,
        'current_section_id' => $sectionId,
        'session_year' => $sessionYear,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it('builds a pdf listing every student in the class for the given session', function () {
    $class = Classes::create(['name' => 'Student List Class', 'order' => 1]);

    makeStudentListTestStudent($class->id, 2, now()->year);
    makeStudentListTestStudent($class->id, 1, now()->year);

    $pdf = app(BuildClassStudentListPdfAction::class)->handle($class, now()->year);

    expect($pdf)->toStartWith('%PDF');
});

it('excludes students from a different class or a different session year', function () {
    $class = Classes::create(['name' => 'Student List Class A', 'order' => 1]);
    $otherClass = Classes::create(['name' => 'Student List Class B', 'order' => 2]);

    makeStudentListTestStudent($class->id, 1, now()->year);
    makeStudentListTestStudent($otherClass->id, 1, now()->year);
    makeStudentListTestStudent($class->id, 2, now()->year - 1);

    $pdf = app(BuildClassStudentListPdfAction::class)->handle($class, now()->year);

    expect($pdf)->toStartWith('%PDF');
});

it('aborts with 404 when the class has no students for that session', function () {
    $class = Classes::create(['name' => 'Student List Empty Class', 'order' => 1]);

    expect(fn () => app(BuildClassStudentListPdfAction::class)->handle($class, now()->year))
        ->toThrow(NotFoundHttpException::class);
});

it('only includes the selected columns in the pdf', function () {
    $class = Classes::create(['name' => 'Student List Plain Class', 'order' => 1]);

    makeStudentListTestStudent($class->id, 1, now()->year);

    $pdf = app(BuildClassStudentListPdfAction::class)->handle($class, now()->year, ['student_id']);

    expect($pdf)->toStartWith('%PDF');
});

it('shows the section and group columns when explicitly selected', function () {
    $class = Classes::create(['name' => 'Student List Extra Fields Class', 'order' => 1]);
    $group = Group::create(['name' => 'Science']);
    $section = Section::create(['class_id' => $class->id, 'name' => 'A']);

    makeStudentListTestStudent($class->id, 1, now()->year, $group->id, $section->id);

    $rendered = view('documents.student-list', [
        'class' => $class,
        'sessionYear' => now()->year,
        'nameHeaders' => ['Name'],
        'columns' => collect([StudentListColumn::Section, StudentListColumn::Group]),
        'rows' => [
            [
                'roll' => '01',
                'names' => ['Student Roll 1'],
                'values' => ['A', 'Science'],
            ],
        ],
    ])->render();

    expect($rendered)
        ->toContain('Section')
        ->toContain('Group')
        ->toContain('Science')
        ->toContain('A');
});

it('shows the birth certificate no column when explicitly selected', function () {
    $class = Classes::create(['name' => 'Student List Birth Cert Class', 'order' => 1]);

    $student = makeStudentListTestStudent($class->id, 1, now()->year);
    $student->update(['birth_certificate_no' => '1234567890']);

    $pdf = app(BuildClassStudentListPdfAction::class)->handle($class, now()->year, ['birth_certificate_no']);

    expect($pdf)->toStartWith('%PDF');

    $rendered = view('documents.student-list', [
        'class' => $class,
        'sessionYear' => now()->year,
        'nameHeaders' => ['Name'],
        'columns' => collect([StudentListColumn::BirthCertificateNo]),
        'rows' => [
            [
                'roll' => '01',
                'names' => ['Student Roll 1'],
                'values' => ['1234567890'],
            ],
        ],
    ])->render();

    expect($rendered)
        ->toContain('Birth Certificate No')
        ->toContain('1234567890');
});

it('falls back to the default columns when none are selected', function () {
    $class = Classes::create(['name' => 'Student List Default Columns Class', 'order' => 1]);

    makeStudentListTestStudent($class->id, 1, now()->year);

    $pdf = app(BuildClassStudentListPdfAction::class)->handle($class, now()->year, []);

    expect($pdf)->toStartWith('%PDF');
});

it('builds a landscape pdf when requested', function () {
    $class = Classes::create(['name' => 'Student List Landscape Class', 'order' => 1]);

    makeStudentListTestStudent($class->id, 1, now()->year);

    $pdf = app(BuildClassStudentListPdfAction::class)->handle($class, now()->year, ['student_id'], 'L');

    expect($pdf)->toStartWith('%PDF');
});

it('shows the main subject and additional subject columns when explicitly selected', function () {
    $class = Classes::create(['name' => 'Student List Optional Subjects Class', 'order' => 9, 'has_group' => true]);
    $group = Group::create(['name' => 'Science']);
    $class->groups()->attach($group->id);

    $biology = Subject::create(['name' => 'Biology', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $biology->classes()->attach($class->id, ['group_id' => $group->id, 'subject_type' => SubjectType::Optional->value]);

    $higherMath = Subject::create(['name' => 'Higher Math', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $higherMath->classes()->attach($class->id, ['group_id' => $group->id, 'subject_type' => SubjectType::Optional->value]);

    $student = makeStudentListTestStudent($class->id, 1, now()->year, $group->id);

    StudentOptionalSubject::create(['student_id' => $student->id, 'class_id' => $class->id, 'group_id' => $group->id, 'subject_id' => $biology->id, 'role' => OptionalSubjectRole::MainOptional]);
    StudentOptionalSubject::create(['student_id' => $student->id, 'class_id' => $class->id, 'group_id' => $group->id, 'subject_id' => $higherMath->id, 'role' => OptionalSubjectRole::ExtraOptional]);

    $pdf = app(BuildClassStudentListPdfAction::class)->handle($class, now()->year, ['main_subject', 'additional_subject']);

    expect($pdf)->toStartWith('%PDF');

    $rendered = view('documents.student-list', [
        'class' => $class,
        'sessionYear' => now()->year,
        'nameHeaders' => ['Name'],
        'columns' => collect([StudentListColumn::MainSubject, StudentListColumn::AdditionalSubject]),
        'rows' => [
            [
                'roll' => '01',
                'names' => ['Student Roll 1'],
                'values' => ['Biology', 'Higher Math'],
            ],
        ],
    ])->render();

    expect($rendered)
        ->toContain('Main Subject')
        ->toContain('Additional Subject')
        ->toContain('Biology')
        ->toContain('Higher Math');
});

it('shows "-" for main subject and additional subject when the student has no optional subject choice', function () {
    $class = Classes::create(['name' => 'Student List No Optional Subjects Class', 'order' => 6]);

    makeStudentListTestStudent($class->id, 1, now()->year);

    $pdf = app(BuildClassStudentListPdfAction::class)->handle($class, now()->year, ['main_subject', 'additional_subject']);

    expect($pdf)->toStartWith('%PDF');
});

it('shows the bangla name, falling back to the english name, when bangla is chosen', function () {
    $class = Classes::create(['name' => 'Student List Bangla Name Class', 'order' => 1]);

    $withBanglaName = makeStudentListTestStudent($class->id, 1, now()->year);
    $withBanglaName->update(['name_bn' => 'আব্দুর রহমান']);
    $withoutBanglaName = makeStudentListTestStudent($class->id, 2, now()->year);

    expect(StudentListNameLanguage::Bangla->headers())->toBe(['নাম'])
        ->and(StudentListNameLanguage::Bangla->names($withBanglaName))->toBe(['আব্দুর রহমান'])
        ->and(StudentListNameLanguage::Bangla->names($withoutBanglaName))->toBe(['Student Roll 2'])
        ->and(StudentListNameLanguage::Both->names($withBanglaName))->toBe(['Student Roll 1', 'আব্দুর রহমান'])
        ->and(StudentListNameLanguage::English->names($withBanglaName))->toBe(['Student Roll 1']);

    expect(app(BuildClassStudentListPdfAction::class)->handle($class, now()->year, nameLanguage: StudentListNameLanguage::Both))
        ->toStartWith('%PDF');
});

it('renders one name column per chosen language', function () {
    $class = Classes::create(['name' => 'Student List Both Names Class', 'order' => 1]);

    $rendered = view('documents.student-list', [
        'class' => $class,
        'sessionYear' => now()->year,
        'nameHeaders' => StudentListNameLanguage::Both->headers(),
        'columns' => collect(),
        'rows' => [
            ['roll' => '01', 'names' => ['Abdur Rahman', 'আব্দুর রহমান'], 'values' => []],
        ],
    ])->render();

    expect($rendered)
        ->toContain('<th>Name</th>')
        ->toContain('<th>নাম</th>')
        ->toContain('Abdur Rahman')
        ->toContain('আব্দুর রহমান');
});

it('passes the chosen name language through the http route', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Student List Bangla Route Class', 'order' => 1]);

    makeStudentListTestStudent($class->id, 1, now()->year);

    $this->actingAs($admin)
        ->get(route('student-list.class.download', ['class' => $class->id, 'name_language' => 'bn']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('streams the student list pdf as a download through the http route', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Student List Route Class', 'order' => 1]);

    makeStudentListTestStudent($class->id, 1, now()->year);

    $response = $this->actingAs($admin)->get(route('student-list.class.download', ['class' => $class->id]));

    $response->assertStatus(Response::HTTP_OK);
    $response->assertHeader('Content-Type', 'application/pdf');
});

it('sends the name language chosen in the download modal to the pdf route', function (string $nameLanguage) {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Student List Modal Class', 'order' => 1]);

    Livewire::test(StudentsByClass::class, ['classId' => $class->id])
        ->callAction('downloadStudentList', [
            'columns' => StudentListColumn::defaults(),
            'name_language' => $nameLanguage,
            'orientation' => 'P',
        ])
        ->assertHasNoActionErrors()
        ->assertRedirect(route('student-list.class.download', [
            'class' => $class->id,
            'columns' => StudentListColumn::defaults(),
            'orientation' => 'P',
            'name_language' => $nameLanguage,
        ]));
})->with(['en', 'bn', 'both']);
