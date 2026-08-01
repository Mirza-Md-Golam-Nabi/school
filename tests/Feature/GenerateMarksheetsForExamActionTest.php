<?php

use App\Actions\GenerateMarksheetsForExamAction;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Jobs\GenerateMarksheetPdfJob;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Marksheet;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function createMarksheetActionTestStudent(int $classId, StudentStatus $status = StudentStatus::Active): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => random_int(1, 100000),
        'current_class_id' => $classId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => $status,
    ]);
}

function createMarksheetActionTestExam(int $classId): Exam
{
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $classId,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'is_published' => true,
    ]);
}

function createMarksheetActionTestRanking(Exam $exam, int $classId, StudentProfile $student): StudentMeritRanking
{
    return StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'class_id' => $classId,
        'total_marks' => 80,
        'gpa' => 4.0,
    ]);
}

it('creates a marksheet for every active, ranked student and dispatches a generation job', function () {
    Queue::fake();

    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $exam = createMarksheetActionTestExam($class->id);

    $student1 = createMarksheetActionTestStudent($class->id);
    $student2 = createMarksheetActionTestStudent($class->id);
    createMarksheetActionTestRanking($exam, $class->id, $student1);
    createMarksheetActionTestRanking($exam, $class->id, $student2);

    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);

    $result = app(GenerateMarksheetsForExamAction::class)->handle($exam, $admin->id);

    expect($result)->toBe(['created' => 2, 'skipped_already_exists' => 0, 'skipped_no_ranking' => 0]);

    $marksheet1 = Marksheet::where('student_id', $student1->id)->where('exam_id', $exam->id)->first();

    expect($marksheet1)->not->toBeNull()
        ->and($marksheet1->generated_by)->toBe($admin->id)
        ->and($marksheet1->is_generated)->toBeFalse();

    Queue::assertPushed(GenerateMarksheetPdfJob::class, 2);
});

it('skips students who have not had rankings calculated for the exam', function () {
    Queue::fake();

    $class = Classes::create(['name' => 'Class Two', 'order' => 2]);
    $exam = createMarksheetActionTestExam($class->id);

    $ranked = createMarksheetActionTestStudent($class->id);
    $unranked = createMarksheetActionTestStudent($class->id);
    createMarksheetActionTestRanking($exam, $class->id, $ranked);

    $result = app(GenerateMarksheetsForExamAction::class)->handle($exam);

    expect($result)->toBe(['created' => 1, 'skipped_already_exists' => 0, 'skipped_no_ranking' => 1]);

    expect(Marksheet::where('student_id', $unranked->id)->where('exam_id', $exam->id)->exists())->toBeFalse();

    Queue::assertPushed(GenerateMarksheetPdfJob::class, 1);
});

it('skips students outside the exam class and inactive students', function () {
    Queue::fake();

    $class = Classes::create(['name' => 'Class Three', 'order' => 3]);
    $otherClass = Classes::create(['name' => 'Class Four', 'order' => 4]);
    $exam = createMarksheetActionTestExam($class->id);

    $inClass = createMarksheetActionTestStudent($class->id);
    $outOfClass = createMarksheetActionTestStudent($otherClass->id);
    $graduated = createMarksheetActionTestStudent($class->id, StudentStatus::Graduated);

    createMarksheetActionTestRanking($exam, $class->id, $inClass);
    createMarksheetActionTestRanking($exam, $otherClass->id, $outOfClass);
    createMarksheetActionTestRanking($exam, $class->id, $graduated);

    $result = app(GenerateMarksheetsForExamAction::class)->handle($exam);

    expect($result)->toBe(['created' => 1, 'skipped_already_exists' => 0, 'skipped_no_ranking' => 0]);

    Queue::assertPushed(GenerateMarksheetPdfJob::class, 1);
});

it('skips students who already have a marksheet for the exam and does not redispatch', function () {
    Queue::fake();

    $class = Classes::create(['name' => 'Class Five', 'order' => 5]);
    $exam = createMarksheetActionTestExam($class->id);
    $student = createMarksheetActionTestStudent($class->id);
    createMarksheetActionTestRanking($exam, $class->id, $student);

    Marksheet::create(['student_id' => $student->id, 'exam_id' => $exam->id]);

    $result = app(GenerateMarksheetsForExamAction::class)->handle($exam);

    expect($result)->toBe(['created' => 0, 'skipped_already_exists' => 1, 'skipped_no_ranking' => 0])
        ->and(Marksheet::where('student_id', $student->id)->where('exam_id', $exam->id)->count())->toBe(1);

    Queue::assertNotPushed(GenerateMarksheetPdfJob::class);
});
