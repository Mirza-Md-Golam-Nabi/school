<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\UserType;
use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Filament\Teacher\Resources\Exams\Pages\ListExams;
use App\Filament\Teacher\Resources\Exams\Pages\ManageClassExams;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TeacherSubject;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createExamListTestClass(): Classes
{
    return Classes::create(['name' => 'Class '.str()->random(4), 'order' => 1, 'is_active' => true]);
}

function createExamListTestTeacher(string $name): TeacherProfile
{
    return TeacherProfile::create([
        'user_id' => User::factory()->create(['name' => $name, 'user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
}

function assignExamListTestTeacherToClass(TeacherProfile $teacher, Classes $class, int $sessionYear): void
{
    $subject = Subject::create([
        'name' => 'Subject '.str()->random(4),
        'has_mcq' => false,
        'has_written' => true,
        'has_practical' => false,
        'is_active' => true,
    ]);

    TeacherSubject::create([
        'teacher_id' => $teacher->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'session_year' => $sessionYear,
    ]);
}

function createExamListTestExam(Classes $class, bool $isPublished = false): Exam
{
    $examType = ExamType::create(['name' => 'Test '.str()->random(4), 'is_active' => true]);

    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => $isPublished,
    ]);
}

it('only shows a class card for a class the teacher is assigned to teach', function () {
    Filament::setCurrentPanel('teacher');

    $teacher = createExamListTestTeacher('Own Teacher');
    $ownClass = createExamListTestClass();
    $otherClass = createExamListTestClass();
    assignExamListTestTeacherToClass($teacher, $ownClass, now()->year);

    test()->actingAs($teacher->user);

    Livewire::test(ListExams::class)
        ->assertSee($ownClass->name)
        ->assertDontSee($otherClass->name);
});

it('lists exams scoped to the selected class', function () {
    Filament::setCurrentPanel('teacher');

    $teacher = createExamListTestTeacher('Own Teacher');
    $ownClass = createExamListTestClass();
    $otherClass = createExamListTestClass();
    assignExamListTestTeacherToClass($teacher, $ownClass, now()->year);
    assignExamListTestTeacherToClass($teacher, $otherClass, now()->year);

    $ownExam = createExamListTestExam($ownClass);
    $otherExam = createExamListTestExam($otherClass);

    test()->actingAs($teacher->user);

    Livewire::test(ManageClassExams::class, ['classId' => $ownClass->id])
        ->assertCanSeeTableRecords([$ownExam])
        ->assertCanNotSeeTableRecords([$otherExam]);
});

it('blocks a teacher from viewing another class\'s exams via a tampered class param', function () {
    $teacher = createExamListTestTeacher('Own Teacher');
    $ownClass = createExamListTestClass();
    $otherClass = createExamListTestClass();
    assignExamListTestTeacherToClass($teacher, $ownClass, now()->year);

    test()->actingAs($teacher->user);

    $response = test()->get('/teacher/exams/class-exams?class='.$otherClass->id);

    $response->assertForbidden();
});

it('blocks a teacher from viewing an exam record for a class they do not teach', function () {
    Filament::setCurrentPanel('teacher');

    $teacher = createExamListTestTeacher('Own Teacher');
    $otherClass = createExamListTestClass();
    $otherExam = createExamListTestExam($otherClass);

    test()->actingAs($teacher->user);

    $response = test()->get(ExamResource::getUrl('view', ['record' => $otherExam]));

    $response->assertNotFound();
});
