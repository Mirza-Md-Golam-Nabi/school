<?php

use App\Enums\EmploymentStatus;
use App\Enums\UserType;
use App\Models\SalaryStructure;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('closes out the previous ongoing structure the day before a new one takes effect', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);

    $old = SalaryStructure::create([
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacher->id,
        'use_components' => false,
        'flat_amount' => 20000,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
    ]);

    $new = SalaryStructure::create([
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacher->id,
        'use_components' => false,
        'flat_amount' => 25000,
        'effective_from' => '2026-08-01',
        'effective_to' => null,
    ]);

    expect($old->fresh()->effective_to?->toDateString())->toBe('2026-07-31');
    expect($new->fresh()->effective_to)->toBeNull();

    $closeActivity = Activity::where('log_name', 'salary_structure')
        ->where('event', 'updated')
        ->where('subject_id', $old->id)
        ->first();

    expect($closeActivity)->not->toBeNull()
        ->description->toBe('Closed out salary structure for "'.$teacher->user->name.'" (effective to 2026-07-31) — a newer structure now applies.');
});

it('does not touch an already-closed structure', function () {
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);

    $closed = SalaryStructure::create([
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacher->id,
        'use_components' => false,
        'flat_amount' => 15000,
        'effective_from' => '2025-01-01',
        'effective_to' => '2025-12-31',
    ]);

    SalaryStructure::create([
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacher->id,
        'use_components' => false,
        'flat_amount' => 20000,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
    ]);

    expect($closed->fresh()->effective_to?->toDateString())->toBe('2025-12-31');
});

it('does not touch an ongoing structure belonging to a different person', function () {
    $teacherOne = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);
    $teacherTwo = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);

    $other = SalaryStructure::create([
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacherOne->id,
        'use_components' => false,
        'flat_amount' => 18000,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
    ]);

    SalaryStructure::create([
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacherTwo->id,
        'use_components' => false,
        'flat_amount' => 22000,
        'effective_from' => '2026-08-01',
        'effective_to' => null,
    ]);

    expect($other->fresh()->effective_to)->toBeNull();
});

it('does not touch a future-dated ongoing structure when a backdated structure is created', function () {
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);

    $future = SalaryStructure::create([
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacher->id,
        'use_components' => false,
        'flat_amount' => 25000,
        'effective_from' => '2026-12-01',
        'effective_to' => null,
    ]);

    SalaryStructure::create([
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacher->id,
        'use_components' => false,
        'flat_amount' => 15000,
        'effective_from' => '2025-06-01',
        'effective_to' => null,
    ]);

    expect($future->fresh()->effective_to)->toBeNull();
});

it('closes out an ongoing structure for staff too', function () {
    $staff = StaffProfile::factory()->create(['status' => EmploymentStatus::Active]);

    $old = SalaryStructure::create([
        'profileable_type' => StaffProfile::class,
        'profileable_id' => $staff->id,
        'use_components' => false,
        'flat_amount' => 10000,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
    ]);

    SalaryStructure::create([
        'profileable_type' => StaffProfile::class,
        'profileable_id' => $staff->id,
        'use_components' => false,
        'flat_amount' => 12000,
        'effective_from' => '2026-05-01',
        'effective_to' => null,
    ]);

    expect($old->fresh()->effective_to?->toDateString())->toBe('2026-04-30');
});
