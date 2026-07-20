<?php

use App\Actions\GenerateAdmitCardsForExamAction;
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

uses(RefreshDatabase::class);

function createAdmitCardTestStudent(int $classId, StudentStatus $status = StudentStatus::Active): StudentProfile
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

function createAdmitCardTestExam(int $classId): Exam
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

it('creates an admit card for every active student in the exam class and dispatches a generation job', function () {
    Queue::fake();

    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $exam = createAdmitCardTestExam($class->id);

    $student1 = createAdmitCardTestStudent($class->id);
    $student2 = createAdmitCardTestStudent($class->id);
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);

    $result = app(GenerateAdmitCardsForExamAction::class)->handle($exam, $admin->id);

    expect($result)->toBe(['created' => 2, 'skipped' => 0]);

    $card1 = AdmitCard::where('student_id', $student1->id)->where('exam_id', $exam->id)->first();

    expect($card1)->not->toBeNull()
        ->and($card1->generated_by)->toBe($admin->id)
        ->and($card1->is_generated)->toBeFalse();

    expect(AdmitCard::where('student_id', $student2->id)->where('exam_id', $exam->id)->exists())->toBeTrue();

    Queue::assertPushed(GenerateAdmitCardPdfJob::class, 2);
});

it('defaults the page size to A4 and honours a custom page size', function () {
    Queue::fake();

    $class = Classes::create(['name' => 'Class Default Size', 'order' => 1]);
    $exam = createAdmitCardTestExam($class->id);
    $student = createAdmitCardTestStudent($class->id);

    app(GenerateAdmitCardsForExamAction::class)->handle($exam);

    $card = AdmitCard::where('student_id', $student->id)->where('exam_id', $exam->id)->first();

    expect($card->page_size)->toBe('A4');

    $otherClass = Classes::create(['name' => 'Class Custom Size', 'order' => 2]);
    $otherExam = createAdmitCardTestExam($otherClass->id);
    $otherStudent = createAdmitCardTestStudent($otherClass->id);

    app(GenerateAdmitCardsForExamAction::class)->handle($otherExam, pageSize: 'A5');

    $otherCard = AdmitCard::where('student_id', $otherStudent->id)->where('exam_id', $otherExam->id)->first();

    expect($otherCard->page_size)->toBe('A5');
});

it('skips students outside the exam class and inactive students', function () {
    Queue::fake();

    $class = Classes::create(['name' => 'Class Two', 'order' => 2]);
    $otherClass = Classes::create(['name' => 'Class Three', 'order' => 3]);
    $exam = createAdmitCardTestExam($class->id);

    createAdmitCardTestStudent($class->id);
    createAdmitCardTestStudent($otherClass->id);
    createAdmitCardTestStudent($class->id, StudentStatus::Graduated);

    $result = app(GenerateAdmitCardsForExamAction::class)->handle($exam);

    expect($result)->toBe(['created' => 1, 'skipped' => 0]);

    Queue::assertPushed(GenerateAdmitCardPdfJob::class, 1);
});

it('skips students who already have an admit card for the exam and does not redispatch', function () {
    Queue::fake();

    $class = Classes::create(['name' => 'Class Four', 'order' => 4]);
    $exam = createAdmitCardTestExam($class->id);
    $student = createAdmitCardTestStudent($class->id);

    AdmitCard::create(['student_id' => $student->id, 'exam_id' => $exam->id]);

    $result = app(GenerateAdmitCardsForExamAction::class)->handle($exam);

    expect($result)->toBe(['created' => 0, 'skipped' => 1])
        ->and(AdmitCard::where('student_id', $student->id)->where('exam_id', $exam->id)->count())->toBe(1);

    Queue::assertNotPushed(GenerateAdmitCardPdfJob::class);
});
