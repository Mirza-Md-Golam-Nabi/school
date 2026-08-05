<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Resources\Exams\Pages\EditExam;
use App\Filament\Resources\Exams\RelationManagers\SubjectConfigsRelationManager;
use App\Filament\Teacher\Pages\EnterStudentMarks;
use App\Filament\Teacher\Resources\Exams\Pages\ViewExam;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TeacherSubject;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function createMarksTestClass(): Classes
{
    return Classes::create(['name' => 'Class '.str()->random(4), 'order' => 1, 'is_active' => true]);
}

function createMarksTestTeacher(string $name): TeacherProfile
{
    return TeacherProfile::create([
        'user_id' => User::factory()->create(['name' => $name, 'user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
}

function createMarksTestSubject(Classes $class): Subject
{
    $subject = Subject::create(['name' => 'Subject '.str()->random(4), 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    return $subject;
}

function createMarksTestExam(Classes $class): Exam
{
    $examType = ExamType::create(['name' => 'Test '.str()->random(4), 'is_active' => true]);

    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => false,
    ]);
}

function assignMarksTestTeacherToSubject(TeacherProfile $teacher, Classes $class, Subject $subject, int $sessionYear): TeacherSubject
{
    return TeacherSubject::create([
        'teacher_id' => $teacher->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'session_year' => $sessionYear,
    ]);
}

it('lets a teacher assigned to the subject enter and save marks', function () {
    $class = createMarksTestClass();
    $teacher = createMarksTestTeacher('Assigned Teacher');
    $subject = createMarksTestSubject($class);
    $exam = createMarksTestExam($class);
    assignMarksTestTeacherToSubject($teacher, $class, $subject, $exam->session_year);

    test()->actingAs($teacher->user);

    $response = test()->get(route('filament.teacher.pages.enter-student-marks').'?'.http_build_query([
        'examId' => $exam->id,
        'subjectId' => $subject->id,
        'classId' => $class->id,
    ]));

    $response->assertOk();
});

it('blocks a teacher not assigned to the subject from entering marks', function () {
    $class = createMarksTestClass();
    $teacher = createMarksTestTeacher('Unassigned Teacher');
    $subject = createMarksTestSubject($class);
    $exam = createMarksTestExam($class);
    // Deliberately no TeacherSubject assignment for this teacher.

    test()->actingAs($teacher->user);

    $response = test()->get(route('filament.teacher.pages.enter-student-marks').'?'.http_build_query([
        'examId' => $exam->id,
        'subjectId' => $subject->id,
        'classId' => $class->id,
    ]));

    $response->assertForbidden();
});

it('rejects saving marks via a direct component call when not assigned to the subject', function () {
    Filament::setCurrentPanel('teacher');

    $class = createMarksTestClass();
    $teacher = createMarksTestTeacher('Assigned Elsewhere');
    $subject = createMarksTestSubject($class);
    $otherSubject = createMarksTestSubject($class);
    $exam = createMarksTestExam($class);
    // Assigned to a DIFFERENT subject in the same class — not $subject.
    assignMarksTestTeacherToSubject($teacher, $class, $otherSubject, $exam->session_year);

    test()->actingAs($teacher->user);

    // Mount with the assigned subject so it passes, then tamper the
    // URL-bound property directly — mirroring how a client could
    // theoretically resend a different subjectId after mount.
    $component = Livewire::test(EnterStudentMarks::class, [
        'examId' => $exam->id,
        'subjectId' => $otherSubject->id,
        'classId' => $class->id,
    ])->instance();

    $component->subjectId = $subject->id;

    expect(fn () => $component->save())->toThrow(HttpException::class);
});

it('shows the Enter Marks action only for subjects the teacher is assigned to', function () {
    Filament::setCurrentPanel('teacher');

    $class = createMarksTestClass();
    $teacher = createMarksTestTeacher('Panel Teacher');
    $assignedSubject = createMarksTestSubject($class);
    $unassignedSubject = createMarksTestSubject($class);
    $exam = createMarksTestExam($class);
    assignMarksTestTeacherToSubject($teacher, $class, $assignedSubject, $exam->session_year);

    test()->actingAs($teacher->user);

    $assignedConfig = $exam->subjectConfigs()->create([
        'subject_id' => $assignedSubject->id,
        'total_marks' => 100,
        'pass_mark' => 33,
        'written_total' => 100,
    ]);

    $unassignedConfig = $exam->subjectConfigs()->create([
        'subject_id' => $unassignedSubject->id,
        'total_marks' => 100,
        'pass_mark' => 33,
        'written_total' => 100,
    ]);

    Livewire::test(SubjectConfigsRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => ViewExam::class])
        ->assertActionVisible(TestAction::make('enterMarks')->table($assignedConfig))
        ->assertActionHidden(TestAction::make('enterMarks')->table($unassignedConfig));
});

it('always shows the Enter Marks action to admins regardless of TeacherSubject assignment', function () {
    Filament::setCurrentPanel('admin');

    $class = createMarksTestClass();
    $subject = createMarksTestSubject($class);
    $exam = createMarksTestExam($class);

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $config = $exam->subjectConfigs()->create([
        'subject_id' => $subject->id,
        'total_marks' => 100,
        'pass_mark' => 33,
        'written_total' => 100,
    ]);

    Livewire::test(SubjectConfigsRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
        ->assertActionVisible(TestAction::make('enterMarks')->table($config));
});
