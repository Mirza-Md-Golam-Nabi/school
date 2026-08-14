<?php

use App\Actions\GenerateMarksheetsForExamTypeAction;
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

function createExamTypeMarksheetTestStudent(int $classId): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => random_int(1, 100000),
        'current_class_id' => $classId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function createExamTypeMarksheetTestExam(int $classId, ExamType $examType): Exam
{
    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $classId,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'is_published' => true,
    ]);
}

function createExamTypeMarksheetTestRanking(Exam $exam, int $classId, StudentProfile $student): StudentMeritRanking
{
    return StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'class_id' => $classId,
        'total_marks' => 80,
        'gpa' => 4.0,
    ]);
}

it('generates marksheets across every class that has an exam of the selected type', function () {
    Queue::fake();

    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    $classA = Classes::create(['name' => 'Class One', 'order' => 1]);
    $classB = Classes::create(['name' => 'Class Two', 'order' => 2]);

    $examA = createExamTypeMarksheetTestExam($classA->id, $examType);
    $examB = createExamTypeMarksheetTestExam($classB->id, $examType);

    $studentA = createExamTypeMarksheetTestStudent($classA->id);
    $studentB = createExamTypeMarksheetTestStudent($classB->id);
    createExamTypeMarksheetTestRanking($examA, $classA->id, $studentA);
    createExamTypeMarksheetTestRanking($examB, $classB->id, $studentB);

    $result = app(GenerateMarksheetsForExamTypeAction::class)->handle($examType);

    expect($result)->toBe(['created' => 2, 'regenerated' => 0, 'skipped_no_ranking' => 0]);

    expect(Marksheet::where('student_id', $studentA->id)->where('exam_id', $examA->id)->exists())->toBeTrue()
        ->and(Marksheet::where('student_id', $studentB->id)->where('exam_id', $examB->id)->exists())->toBeTrue();

    Queue::assertPushed(GenerateMarksheetPdfJob::class, 2);
});

it('sums regenerated and skipped-no-ranking counts across classes', function () {
    Queue::fake();

    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    $classA = Classes::create(['name' => 'Class Three', 'order' => 3]);
    $classB = Classes::create(['name' => 'Class Four', 'order' => 4]);

    $examA = createExamTypeMarksheetTestExam($classA->id, $examType);
    $examB = createExamTypeMarksheetTestExam($classB->id, $examType);

    $rankedA = createExamTypeMarksheetTestStudent($classA->id);
    $unrankedA = createExamTypeMarksheetTestStudent($classA->id);
    createExamTypeMarksheetTestRanking($examA, $classA->id, $rankedA);
    Marksheet::create(['student_id' => $rankedA->id, 'exam_id' => $examA->id]);

    $rankedB = createExamTypeMarksheetTestStudent($classB->id);
    createExamTypeMarksheetTestRanking($examB, $classB->id, $rankedB);

    $result = app(GenerateMarksheetsForExamTypeAction::class)->handle($examType);

    expect($result)->toBe(['created' => 1, 'regenerated' => 1, 'skipped_no_ranking' => 1]);
});

it('never touches marksheets belonging to a different exam type', function () {
    Queue::fake();

    $classTest = ExamType::create(['name' => 'Class Test', 'is_active' => true]);
    $halfYearly = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $class = Classes::create(['name' => 'Class Five', 'order' => 5]);

    $classTestExam = createExamTypeMarksheetTestExam($class->id, $classTest);
    $student = createExamTypeMarksheetTestStudent($class->id);
    createExamTypeMarksheetTestRanking($classTestExam, $class->id, $student);

    $existingClassTestMarksheet = Marksheet::create([
        'student_id' => $student->id,
        'exam_id' => $classTestExam->id,
        'is_generated' => true,
        'file_path' => 'documents/marksheets/keep-me.pdf',
    ]);

    $halfYearlyExam = createExamTypeMarksheetTestExam($class->id, $halfYearly);
    createExamTypeMarksheetTestRanking($halfYearlyExam, $class->id, $student);

    app(GenerateMarksheetsForExamTypeAction::class)->handle($halfYearly);

    $existingClassTestMarksheet->refresh();

    expect($existingClassTestMarksheet->is_generated)->toBeTrue()
        ->and($existingClassTestMarksheet->file_path)->toBe('documents/marksheets/keep-me.pdf');
});
