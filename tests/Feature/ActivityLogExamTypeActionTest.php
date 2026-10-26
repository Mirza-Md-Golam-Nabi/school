<?php

use App\Actions\CreateExamTypeAction;
use App\Actions\UpdateExamTypeAction;
use App\Enums\CountMethod;
use App\Enums\ExamConfigType;
use App\Enums\UserType;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Models\Classes;
use App\Models\ExamType;
use App\Models\ResultRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('logs the creation of an exam type as a single row', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $examType = app(CreateExamTypeAction::class)->handle([
        'name' => 'Class Test',
        'type' => ExamConfigType::General->value,
        'count_method' => CountMethod::BestN->value,
        'best_n_count' => 2,
    ]);

    $activities = Activity::where('log_name', 'exam_type')
        ->where('event', 'created')
        ->where('subject_id', $examType->id)
        ->get();

    expect($activities)->toHaveCount(1)
        ->and($activities->first()->description)->toBe('Created exam type "Class Test".')
        ->and($activities->first()->properties->get('attributes'))->toMatchArray([
            'name' => 'Class Test',
            'type' => 'general',
            'count_method' => 'best_n',
            'best_n_count' => 2,
        ]);
});

it('logs the update of an exam type as a single row with old and new values', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $examType = app(CreateExamTypeAction::class)->handle([
        'name' => 'Class Test',
        'type' => ExamConfigType::General->value,
        'count_method' => CountMethod::All->value,
        'best_n_count' => null,
    ]);

    app(UpdateExamTypeAction::class)->handle($examType, [
        'name' => 'Class Test (Renamed)',
        'type' => ExamConfigType::Main->value,
        'count_method' => CountMethod::All->value,
        'best_n_count' => null,
    ]);

    $activities = Activity::where('log_name', 'exam_type')
        ->where('event', 'updated')
        ->where('subject_id', $examType->id)
        ->get();

    expect($activities)->toHaveCount(1);

    $activity = $activities->first();

    expect($activity->description)->toBe('Updated exam type "Class Test (Renamed)".')
        ->and($activity->properties->get('attributes'))->toMatchArray(['name' => 'Class Test (Renamed)', 'type' => 'main'])
        ->and($activity->properties->get('old'))->toMatchArray(['name' => 'Class Test', 'type' => 'general']);
});

it('renders the activity log detail page without error for array-valued attributes', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 6', 'order' => 6]);
    $rule = ResultRule::factory()->create();
    $rule->classes()->attach($class);
    $rule->components()->create(['exam_type_id' => $rule->target_exam_type_id, 'weight_percent' => 100]);
    $rule->logActivity('created');

    $activity = Activity::where('log_name', 'result_rule')->where('subject_id', $rule->id)->firstOrFail();

    $this->get(ActivityLogResource::getUrl('view', ['record' => $activity->id]))
        ->assertOk()
        ->assertSee('Class 6');
});
