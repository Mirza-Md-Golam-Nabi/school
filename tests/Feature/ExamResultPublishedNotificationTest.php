<?php

use App\Actions\CreateExamAction;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\Exams\Pages\EditExam;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\ExamResultPublishedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createExamNotificationTestStudent(Classes $class, int $rollNo): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it('notifies every active student in the class when an exam is created already published', function () {
    Notification::fake();

    $class = Classes::create(['name' => 'Class 10', 'order' => 10, 'is_active' => true]);
    $examType = ExamType::create(['name' => 'Final', 'is_active' => true]);
    $student1 = createExamNotificationTestStudent($class, 1);
    $student2 = createExamNotificationTestStudent($class, 2);

    $exam = app(CreateExamAction::class)->handle([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => true,
    ]);

    Notification::assertSentTo(
        $student1->user,
        ExamResultPublishedNotification::class,
        fn (ExamResultPublishedNotification $n) => $n->exam->is($exam),
    );
    Notification::assertSentTo($student2->user, ExamResultPublishedNotification::class);
});

it('does not notify anyone when an exam is created unpublished', function () {
    Notification::fake();

    $class = Classes::create(['name' => 'Class 10', 'order' => 10, 'is_active' => true]);
    $examType = ExamType::create(['name' => 'Final', 'is_active' => true]);
    $student = createExamNotificationTestStudent($class, 1);

    app(CreateExamAction::class)->handle([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => false,
    ]);

    Notification::assertNothingSentTo($student->user);
});

it('does not notify students of other classes', function () {
    Notification::fake();

    $class = Classes::create(['name' => 'Class 10', 'order' => 10, 'is_active' => true]);
    $otherClass = Classes::create(['name' => 'Class 11', 'order' => 11, 'is_active' => true]);
    $examType = ExamType::create(['name' => 'Final', 'is_active' => true]);
    createExamNotificationTestStudent($class, 1);
    $otherStudent = createExamNotificationTestStudent($otherClass, 1);

    app(CreateExamAction::class)->handle([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => true,
    ]);

    Notification::assertNothingSentTo($otherStudent->user);
});

it('notifies students when an admin toggles an exam from unpublished to published', function () {
    Notification::fake();

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 10', 'order' => 10, 'is_active' => true]);
    $examType = ExamType::create(['name' => 'Final', 'is_active' => true]);
    $student = createExamNotificationTestStudent($class, 1);

    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => false,
    ]);

    Livewire::test(EditExam::class, ['record' => $exam->id])
        ->fillForm(['is_published' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($student->user, ExamResultPublishedNotification::class);
});

it('does not re-notify when an already-published exam is resaved', function () {
    Notification::fake();

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 10', 'order' => 10, 'is_active' => true]);
    $examType = ExamType::create(['name' => 'Final', 'is_active' => true]);
    $student = createExamNotificationTestStudent($class, 1);

    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => true,
    ]);

    Livewire::test(EditExam::class, ['record' => $exam->id])
        ->fillForm(['is_published' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    Notification::assertNothingSentTo($student->user);
});

it('does not notify when an exam is unpublished', function () {
    Notification::fake();

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 10', 'order' => 10, 'is_active' => true]);
    $examType = ExamType::create(['name' => 'Final', 'is_active' => true]);
    $student = createExamNotificationTestStudent($class, 1);

    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => true,
    ]);

    Livewire::test(EditExam::class, ['record' => $exam->id])
        ->fillForm(['is_published' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    Notification::assertNothingSentTo($student->user);
});
