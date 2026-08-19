<?php

use App\Actions\GenerateAdmitCardsForExamAction;
use App\Actions\GenerateAdmitCardsForExamTypeAction;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function createAdmitCardLogTestStudent(int $classId, StudentStatus $status = StudentStatus::Active): StudentProfile
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

function createAdmitCardLogTestExam(int $classId, ExamType $examType): Exam
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

it('logs a single summary entry with a readable exam label when admit cards are generated for an exam', function () {
    Queue::fake();

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $exam = createAdmitCardLogTestExam($class->id, $examType);

    createAdmitCardLogTestStudent($class->id);
    createAdmitCardLogTestStudent($class->id);

    app(GenerateAdmitCardsForExamAction::class)->handle($exam, $admin->id, 'A5');

    $activities = Activity::where('log_name', 'admit_card_generation')->where('event', 'generated')->get();

    expect($activities)->toHaveCount(1);

    $activity = $activities->first();
    $examLabel = $exam->displayLabel();

    expect($activity->causer_id)->toBe($admin->id)
        ->and($activity->subject_id)->toBe($exam->id)
        ->and($activity->description)->toBe("Generated 2 admit card(s) for \"{$examLabel}\" — 0 skipped (already exists).");

    expect($activity->properties->get('attributes'))
        ->toMatchArray([
            'exam_id' => $exam->id,
            'exam_id_label' => $examLabel,
            'page_size' => 'A5',
            'created_count' => 2,
            'skipped_count' => 0,
        ]);
});

it('logs a clear summary when switching exam types wipes admit cards from the previous exam type', function () {
    Queue::fake();

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class Two', 'order' => 2]);
    $firstExamType = ExamType::create(['name' => 'Class Test', 'is_active' => true]);
    $firstExam = createAdmitCardLogTestExam($class->id, $firstExamType);

    createAdmitCardLogTestStudent($class->id);

    app(GenerateAdmitCardsForExamAction::class)->handle($firstExam, $admin->id);

    $secondExamType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);
    $secondExam = createAdmitCardLogTestExam($class->id, $secondExamType);

    app(GenerateAdmitCardsForExamTypeAction::class)->handle($secondExamType, $admin->id);

    $clearActivity = Activity::where('log_name', 'admit_card_generation')->where('event', 'deleted')->first();

    expect($clearActivity)->not->toBeNull()
        ->and($clearActivity->subject_id)->toBe($secondExamType->id)
        ->and($clearActivity->description)->toBe(
            "Cleared 1 existing admit card(s) from other exam types before generating \"{$secondExamType->name}\" admit cards."
        );

    expect($clearActivity->properties->get('attributes'))
        ->toMatchArray([
            'exam_type_id' => $secondExamType->id,
            'exam_type_id_label' => $secondExamType->name,
            'cleared_count' => 1,
        ]);

    $generatedActivity = Activity::where('log_name', 'admit_card_generation')
        ->where('event', 'generated')
        ->where('subject_id', $secondExam->id)
        ->first();

    expect($generatedActivity)->not->toBeNull()
        ->description->toBe('Generated 1 admit card(s) for "'.$secondExam->displayLabel().'" — 0 skipped (already exists).');
});
