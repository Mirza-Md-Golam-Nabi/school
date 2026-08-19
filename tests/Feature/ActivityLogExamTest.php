<?php

use App\Enums\UserType;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('logs create, update, and delete of an exam with relation labels and plain dates', function () {
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

    $createdActivity = Activity::where('log_name', 'exam')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->causer_id->toBe($admin->id)
        ->description->toBe('Created exam "Half Yearly - Class 8 (2026)".');

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'exam_type_id' => $examType->id,
            'exam_type_id_label' => 'Half Yearly',
            'class_id' => $class->id,
            'class_id_label' => 'Class 8',
            'start_date' => '2026-08-22',
            'end_date' => '2026-08-26',
        ]);

    expect($exam->fresh()->getRawOriginal('start_date'))->toBe('2026-08-22');
    expect($exam->fresh()->getRawOriginal('end_date'))->toBe('2026-08-26');

    $exam->update(['end_date' => '2026-08-27']);

    $updatedActivity = Activity::where('log_name', 'exam')->where('event', 'updated')->first();

    expect($updatedActivity)->not->toBeNull();
    expect($updatedActivity->properties->get('attributes'))
        ->toMatchArray(['end_date' => '2026-08-27']);
    expect($updatedActivity->description)->toBe('Updated exam "Half Yearly - Class 8 (2026)".');

    $exam->delete();

    expect(Activity::where('log_name', 'exam')->where('event', 'deleted')->first())
        ->not->toBeNull()
        ->description->toBe('Deleted exam "Half Yearly - Class 8 (2026)".');
});
