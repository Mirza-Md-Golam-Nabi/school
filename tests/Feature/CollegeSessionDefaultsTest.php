<?php

use App\Actions\ImportStudentsFromSpreadsheetAction;
use App\Enums\ClassLevel;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\StudentIdCardField;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\Classes\Pages\EditClasses;
use App\Filament\Resources\Classes\RelationManagers\TeacherSubjectsRelationManager;
use App\Filament\Resources\Exams\Pages\CreateExam;
use App\Filament\Resources\Exams\Pages\ExamsByClass;
use App\Filament\Resources\StudentProfiles\Pages\StudentsByClass;
use App\Filament\Teacher\Pages\MySubjects;
use App\Filament\Teacher\Widgets\TeacherSubjectsOverview;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TeacherSubject;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->college = Classes::create(['name' => 'Class 11', 'order' => 11, 'level' => ClassLevel::College, 'is_active' => true]);
    $this->school = Classes::create(['name' => 'Class 9', 'order' => 9, 'level' => ClassLevel::Secondary, 'is_active' => true]);
});

function makeCollegeSessionStudent(Classes $class, int $rollNo, int $sessionYear): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student])->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => $sessionYear,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function makeCollegeSessionTeacher(): TeacherProfile
{
    return TeacherProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
}

it('keeps last July\'s college students in the class list until June', function () {
    $this->travelTo(Carbon::parse('2027-02-10'));
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $current = makeCollegeSessionStudent($this->college, 1, 2026);
    $upcoming = makeCollegeSessionStudent($this->college, 2, 2027);

    Livewire::test(StudentsByClass::class, ['classId' => $this->college->id])
        ->assertCanSeeTableRecords([$current])
        ->assertCanNotSeeTableRecords([$upcoming]);
});

it('shows each class on its own current session in the teacher\'s subjects', function () {
    $this->travelTo(Carbon::parse('2027-02-10'));

    $teacher = makeCollegeSessionTeacher();
    $physics = Subject::create(['name' => 'Physics']);
    $math = Subject::create(['name' => 'Math']);

    TeacherSubject::create(['teacher_id' => $teacher->id, 'subject_id' => $physics->id, 'class_id' => $this->college->id, 'session_year' => 2026]);
    TeacherSubject::create(['teacher_id' => $teacher->id, 'subject_id' => $math->id, 'class_id' => $this->school->id, 'session_year' => 2027]);
    TeacherSubject::create(['teacher_id' => $teacher->id, 'subject_id' => $physics->id, 'class_id' => $this->school->id, 'session_year' => 2026]);

    $this->actingAs($teacher->user);
    Filament::setCurrentPanel('teacher');

    $page = Livewire::test(MySubjects::class)
        ->assertSee('Session 2026-27')
        ->assertSee('Session 2027');

    expect($page->instance()->classesBySubjects()->pluck('class.id')->sort()->values()->all())
        ->toBe(collect([$this->college->id, $this->school->id])->sort()->values()->all());

    $page->set('sessionYear', 2026);

    expect($page->instance()->classesBySubjects()->map(fn (array $item): int => $item['subjects']->count())->sum())->toBe(2);

    $overview = (new TeacherSubjectsOverview)->getViewData();

    expect($overview['classCount'])->toBe(2)
        ->and($overview['subjectCount'])->toBe(2);
});

it('defaults a college teacher assignment to the running college session', function () {
    $this->travelTo(Carbon::parse('2027-02-10'));
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    Livewire::test(TeacherSubjectsRelationManager::class, ['ownerRecord' => $this->college, 'pageClass' => EditClasses::class])
        ->mountAction(TestAction::make('create')->table())
        ->assertSchemaStateSet(['session_year' => 2026]);

    Livewire::test(TeacherSubjectsRelationManager::class, ['ownerRecord' => $this->school, 'pageClass' => EditClasses::class])
        ->mountAction(TestAction::make('create')->table())
        ->assertSchemaStateSet(['session_year' => 2027]);
});

it('sets the exam session from the picked class and refuses to mix college and school sessions', function () {
    $this->travelTo(Carbon::parse('2027-02-10'));
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    Livewire::test(CreateExam::class)
        ->fillForm(['class_id' => [$this->college->id]])
        ->assertSchemaStateSet(['session_year' => 2026])
        ->fillForm([
            'exam_type_id' => $examType->id,
            'class_id' => [$this->college->id, $this->school->id],
            'session_year' => 2027,
            'start_date' => '2027-03-01',
            'end_date' => '2027-03-10',
        ])
        ->call('create')
        ->assertHasFormErrors(['class_id']);

    $this->travelTo(Carbon::parse('2027-09-10'));

    Livewire::test(CreateExam::class)
        ->fillForm([
            'exam_type_id' => $examType->id,
            'class_id' => [$this->college->id, $this->school->id],
            'session_year' => 2027,
            'start_date' => '2027-10-01',
            'end_date' => '2027-10-10',
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});

it('prefills the college session when an exam is created from the class page', function () {
    $this->travelTo(Carbon::parse('2027-02-10'));
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    Livewire::withQueryParams(['classId' => $this->college->id])
        ->test(CreateExam::class)
        ->assertSchemaStateSet(['class_id' => [$this->college->id], 'session_year' => 2026]);
});

it('prefills the import template with the class\'s running session', function () {
    $this->travelTo(Carbon::parse('2027-02-10'));

    expect(ImportStudentsFromSpreadsheetAction::prefilledValues($this->college)['session_year'])->toBe(2026)
        ->and(ImportStudentsFromSpreadsheetAction::prefilledValues($this->school)['session_year'])->toBe(2027)
        ->and(ImportStudentsFromSpreadsheetAction::prefilledValues()['session_year'])->toBe(2027);
});

it('labels college sessions across two years wherever a session is shown', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $collegeExam = Exam::create(['exam_type_id' => $examType->id, 'class_id' => $this->college->id, 'session_year' => 2026, 'start_date' => '2026-12-01', 'end_date' => '2026-12-10']);
    $schoolExam = Exam::create(['exam_type_id' => $examType->id, 'class_id' => $this->school->id, 'session_year' => 2026, 'start_date' => '2026-12-01', 'end_date' => '2026-12-10']);
    $collegeStudent = makeCollegeSessionStudent($this->college, 1, 2026);

    expect($collegeExam->session_label)->toBe('2026-27')
        ->and($collegeExam->displayLabel())->toBe('Half Yearly - Class 11 (2026-27)')
        ->and($schoolExam->session_label)->toBe('2026')
        ->and($collegeStudent->session_label)->toBe('2026-27')
        ->and(StudentIdCardField::Session->valueFor($collegeStudent))->toBe('2026-27');

    Livewire::test(ExamsByClass::class, ['classId' => $this->college->id])
        ->assertTableColumnFormattedStateSet('session_year', '2026-27', $collegeExam);
});
