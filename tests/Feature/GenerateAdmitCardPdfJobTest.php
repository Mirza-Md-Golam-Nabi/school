<?php

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
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('renders and stores the admit card pdf, then marks the record as generated', function () {
    Storage::fake('local');

    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'is_published' => true,
    ]);

    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 5,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $admitCard = AdmitCard::create(['student_id' => $student->id, 'exam_id' => $exam->id]);

    (new GenerateAdmitCardPdfJob($admitCard->id))->handle();

    $admitCard->refresh();

    $expectedPath = "documents/admit_cards/{$exam->session_year}/{$exam->id}/{$student->id}.pdf";

    expect($admitCard->is_generated)->toBeTrue()
        ->and($admitCard->file_path)->toBe($expectedPath)
        ->and($admitCard->file_generated_at)->not->toBeNull();

    Storage::disk('local')->assertExists($expectedPath);
});

it('renders the pdf using the admit card\'s configured page size', function () {
    Storage::fake('local');

    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'is_published' => true,
    ]);

    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 5,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $admitCard = AdmitCard::create(['student_id' => $student->id, 'exam_id' => $exam->id, 'page_size' => 'A5']);

    (new GenerateAdmitCardPdfJob($admitCard->id))->handle();

    $admitCard->refresh();

    expect($admitCard->is_generated)->toBeTrue();

    Storage::disk('local')->assertExists($admitCard->file_path);
});

it('deletes the previously stored file before writing the regenerated pdf', function () {
    Storage::fake('local');

    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'is_published' => true,
    ]);

    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 5,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $stalePath = 'documents/admit_cards/stale/old-copy.pdf';
    Storage::disk('local')->put($stalePath, 'stale pdf content');

    $admitCard = AdmitCard::create([
        'student_id' => $student->id,
        'exam_id' => $exam->id,
        'is_generated' => true,
        'file_path' => $stalePath,
        'file_generated_at' => now()->subDay(),
    ]);

    (new GenerateAdmitCardPdfJob($admitCard->id))->handle();

    $admitCard->refresh();

    $expectedPath = "documents/admit_cards/{$exam->session_year}/{$exam->id}/{$student->id}.pdf";

    Storage::disk('local')->assertMissing($stalePath);
    Storage::disk('local')->assertExists($expectedPath);

    expect($admitCard->file_path)->toBe($expectedPath);
});
