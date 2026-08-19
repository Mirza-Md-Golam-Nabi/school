<?php

use App\Enums\UserType;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('logs create, update, and delete of an exam subject config with relation labels', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 8', 'order' => 8]);
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => 2026,
        'start_date' => '2026-08-22',
        'end_date' => '2026-08-26',
        'is_published' => false,
    ]);

    $subject = Subject::create([
        'name' => 'Physics',
        'has_mcq' => true,
        'has_written' => true,
        'has_practical' => false,
        'is_active' => true,
    ]);

    $config = ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'mcq_total' => 30,
        'written_total' => 70,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);

    $createdActivity = Activity::where('log_name', 'exam_subject_config')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->causer_id->toBe($admin->id)
        ->description->toBe('Created subject config for "Physics" in "Half Yearly - Class 8 (2026)".');

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'exam_id' => $exam->id,
            'exam_id_label' => 'Half Yearly - Class 8 (2026)',
            'subject_id' => $subject->id,
            'subject_id_label' => 'Physics',
        ]);

    $config->update(['total_marks' => 90]);

    expect(Activity::where('log_name', 'exam_subject_config')->where('event', 'updated')->first())
        ->not->toBeNull()
        ->description->toBe('Updated subject config for "Physics" in "Half Yearly - Class 8 (2026)".');

    $config->delete();

    expect(Activity::where('log_name', 'exam_subject_config')->where('event', 'deleted')->first())
        ->not->toBeNull()
        ->description->toBe('Deleted subject config for "Physics" in "Half Yearly - Class 8 (2026)".');
});
