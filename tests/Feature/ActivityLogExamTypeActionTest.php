<?php

use App\Actions\CreateExamTypeAction;
use App\Actions\UpdateExamTypeAction;
use App\Enums\CountMethod;
use App\Enums\ExamConfigType;
use App\Enums\UserType;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Models\Classes;
use App\Models\ExamType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('logs the creation of a supporting exam type as a single row with readable labels', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 6', 'order' => 6]);
    $targetExamType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    $examType = app(CreateExamTypeAction::class)->handle([
        'name' => 'Class Test 1',
        'type' => ExamConfigType::Supporting->value,
        'count_method' => CountMethod::All->value,
        'best_n_count' => null,
        'class_id' => [$class->id],
        'target_exam_type_id' => $targetExamType->id,
        'contribution_percent' => 10,
        'session_year' => now()->year,
    ]);

    $activities = Activity::where('log_name', 'exam_type')
        ->where('event', 'created')
        ->where('subject_id', $examType->id)
        ->get();

    expect($activities)->toHaveCount(1);

    expect($activities->first()->description)->toBe('Created exam type "Class Test 1".');

    $attributes = $activities->first()->properties->get('attributes');

    expect($attributes)->toMatchArray([
        'name' => 'Class Test 1',
        'type' => 'supporting',
        'count_method' => 'all',
        'class_id' => [$class->id],
        'class_id_label' => ['Class 6'],
        'target_exam_type_id' => $targetExamType->id,
        'target_exam_type_id_label' => 'Half Yearly',
        'contribution_percent' => 10,
    ]);
});

it('logs the update of an exam type as a single row with old and new values', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $classOne = Classes::create(['name' => 'Class 6', 'order' => 6]);
    $classTwo = Classes::create(['name' => 'Class 7', 'order' => 7]);
    $targetExamType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    $examType = app(CreateExamTypeAction::class)->handle([
        'name' => 'Class Test 1',
        'type' => ExamConfigType::Supporting->value,
        'count_method' => CountMethod::All->value,
        'best_n_count' => null,
        'class_id' => [$classOne->id],
        'target_exam_type_id' => $targetExamType->id,
        'contribution_percent' => 10,
        'session_year' => now()->year,
    ]);

    app(UpdateExamTypeAction::class)->handle($examType, [
        'name' => 'Class Test 1 (Renamed)',
        'type' => ExamConfigType::Supporting->value,
        'count_method' => CountMethod::All->value,
        'best_n_count' => null,
        'class_id' => [$classTwo->id],
        'target_exam_type_id' => $targetExamType->id,
        'contribution_percent' => 20,
        'session_year' => now()->year,
    ]);

    $activities = Activity::where('log_name', 'exam_type')
        ->where('event', 'updated')
        ->where('subject_id', $examType->id)
        ->get();

    expect($activities)->toHaveCount(1);

    $activity = $activities->first();

    expect($activity->description)->toBe('Updated exam type "Class Test 1 (Renamed)".');

    expect($activity->properties->get('attributes'))->toMatchArray([
        'name' => 'Class Test 1 (Renamed)',
        'class_id' => [$classTwo->id],
        'class_id_label' => ['Class 7'],
        'contribution_percent' => 20,
    ]);

    expect($activity->properties->get('old'))->toMatchArray([
        'name' => 'Class Test 1',
        'class_id' => [$classOne->id],
        'class_id_label' => ['Class 6'],
        'contribution_percent' => 10,
    ]);
});

it('renders the activity log detail page without error for array-valued attributes', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 6', 'order' => 6]);
    $targetExamType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    $examType = app(CreateExamTypeAction::class)->handle([
        'name' => 'Class Test 1',
        'type' => ExamConfigType::Supporting->value,
        'count_method' => CountMethod::All->value,
        'best_n_count' => null,
        'class_id' => [$class->id],
        'target_exam_type_id' => $targetExamType->id,
        'contribution_percent' => 10,
        'session_year' => now()->year,
    ]);

    $activity = Activity::where('log_name', 'exam_type')
        ->where('event', 'created')
        ->where('subject_id', $examType->id)
        ->firstOrFail();

    $this->get(ActivityLogResource::getUrl('view', ['record' => $activity->id]))
        ->assertOk()
        ->assertSee('Class 6');
});
