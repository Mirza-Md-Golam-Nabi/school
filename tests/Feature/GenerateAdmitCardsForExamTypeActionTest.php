<?php

use App\Actions\GenerateAdmitCardsForExamTypeAction;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Jobs\GenerateAdmitCardPdfJob;
use App\Models\AdmitCard;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createExamTypeTestStudent(int $classId): StudentProfile
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

function createExamTypeTestExam(int $classId, ExamType $examType, bool $isPublished = true): Exam
{
    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $classId,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'is_published' => $isPublished,
    ]);
}

it('generates admit cards across every class that has an exam of the selected type', function () {
    Queue::fake();

    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    $classA = Classes::create(['name' => 'Class One', 'order' => 1]);
    $classB = Classes::create(['name' => 'Class Two', 'order' => 2]);

    $examA = createExamTypeTestExam($classA->id, $examType);
    $examB = createExamTypeTestExam($classB->id, $examType);

    $studentA = createExamTypeTestStudent($classA->id);
    $studentB = createExamTypeTestStudent($classB->id);

    $result = app(GenerateAdmitCardsForExamTypeAction::class)->handle($examType);

    expect($result)->toBe(['created' => 2, 'skipped' => 0]);

    expect(AdmitCard::where('student_id', $studentA->id)->where('exam_id', $examA->id)->exists())->toBeTrue()
        ->and(AdmitCard::where('student_id', $studentB->id)->where('exam_id', $examB->id)->exists())->toBeTrue();

    Queue::assertPushed(GenerateAdmitCardPdfJob::class, 2);
});

it('passes the chosen page size down to every generated admit card', function () {
    Queue::fake();

    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $class = Classes::create(['name' => 'Class Page Size', 'order' => 1]);

    $exam = createExamTypeTestExam($class->id, $examType);
    $student = createExamTypeTestStudent($class->id);

    app(GenerateAdmitCardsForExamTypeAction::class)->handle($examType, pageSize: 'A5');

    $card = AdmitCard::where('student_id', $student->id)->where('exam_id', $exam->id)->first();

    expect($card->page_size)->toBe('A5');
});

it('generates admit cards even for unpublished exams of the selected type', function () {
    Queue::fake();

    $examType = ExamType::create(['name' => 'Final Exam', 'is_active' => true]);
    $class = Classes::create(['name' => 'Class Three', 'order' => 3]);

    $exam = createExamTypeTestExam($class->id, $examType, isPublished: false);
    $student = createExamTypeTestStudent($class->id);

    $result = app(GenerateAdmitCardsForExamTypeAction::class)->handle($examType);

    expect($result)->toBe(['created' => 1, 'skipped' => 0]);

    expect(AdmitCard::where('student_id', $student->id)->where('exam_id', $exam->id)->exists())->toBeTrue();

    Queue::assertPushed(GenerateAdmitCardPdfJob::class, 1);
});

it('does not recreate admit cards for students who already have one', function () {
    Queue::fake();

    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $class = Classes::create(['name' => 'Class Four', 'order' => 4]);
    $exam = createExamTypeTestExam($class->id, $examType);
    $student = createExamTypeTestStudent($class->id);

    AdmitCard::create(['student_id' => $student->id, 'exam_id' => $exam->id]);

    $result = app(GenerateAdmitCardsForExamTypeAction::class)->handle($examType);

    expect($result)->toBe(['created' => 0, 'skipped' => 1])
        ->and(AdmitCard::where('student_id', $student->id)->where('exam_id', $exam->id)->count())->toBe(1);

    Queue::assertNotPushed(GenerateAdmitCardPdfJob::class);
});

it('wipes a previously generated exam type\'s admit cards and files when switching to a different exam type', function () {
    Storage::fake('local');
    Queue::fake();

    $halfYearly = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $annual = ExamType::create(['name' => 'Annual Exam', 'is_active' => true]);
    $class = Classes::create(['name' => 'Class Five', 'order' => 5]);

    $halfYearlyExam = createExamTypeTestExam($class->id, $halfYearly);
    $student = createExamTypeTestStudent($class->id);

    $oldFilePath = "documents/admit_cards/{$halfYearlyExam->session_year}/{$halfYearlyExam->id}/{$student->id}.pdf";
    Storage::disk('local')->put($oldFilePath, 'old pdf content');

    $oldAdmitCard = AdmitCard::create([
        'student_id' => $student->id,
        'exam_id' => $halfYearlyExam->id,
        'is_generated' => true,
        'file_path' => $oldFilePath,
        'file_generated_at' => now(),
    ]);

    $annualExam = createExamTypeTestExam($class->id, $annual);

    $result = app(GenerateAdmitCardsForExamTypeAction::class)->handle($annual);

    expect($result)->toBe(['created' => 1, 'skipped' => 0])
        ->and(AdmitCard::find($oldAdmitCard->id))->toBeNull()
        ->and(AdmitCard::where('student_id', $student->id)->where('exam_id', $annualExam->id)->exists())->toBeTrue();

    Storage::disk('local')->assertMissing($oldFilePath);
});

it('wipes a previously requested exam type\'s pending (not yet generated) admit cards too', function () {
    Queue::fake();

    $halfYearly = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $annual = ExamType::create(['name' => 'Annual Exam', 'is_active' => true]);
    $class = Classes::create(['name' => 'Class Six', 'order' => 6]);

    $halfYearlyExam = createExamTypeTestExam($class->id, $halfYearly);
    $student = createExamTypeTestStudent($class->id);

    $pendingAdmitCard = AdmitCard::create([
        'student_id' => $student->id,
        'exam_id' => $halfYearlyExam->id,
        'is_generated' => false,
    ]);

    createExamTypeTestExam($class->id, $annual);

    app(GenerateAdmitCardsForExamTypeAction::class)->handle($annual);

    expect(AdmitCard::find($pendingAdmitCard->id))->toBeNull();
});
