<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\LeaveApplicability;
use App\Enums\UserType;
use App\Filament\Resources\LeaveApplications\Pages\CreateLeaveApplication;
use App\Filament\Teacher\Pages\LeaveApplications;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\LeaveTypeAssignment;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('hides a gender-specific leave type from a teacher until it is assigned', function () {
    $user = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);
    $teacher = TeacherProfile::create([
        'user_id' => $user->id,
        'gender' => Gender::Female,
        'status' => EmploymentStatus::Active,
    ]);

    $leaveType = LeaveType::factory()->create(['applicable_gender' => LeaveApplicability::Female]);

    expect(LeaveType::query()->availableFor($teacher)->pluck('id'))->not->toContain($leaveType->id)
        ->and($leaveType->requiresAssignment())->toBeTrue()
        ->and($leaveType->isAssignedTo($teacher))->toBeFalse();

    LeaveTypeAssignment::ensureAssigned($leaveType, $teacher);

    expect(LeaveType::query()->availableFor($teacher)->pluck('id'))->toContain($leaveType->id)
        ->and($leaveType->isAssignedTo($teacher))->toBeTrue();
});

it('does not gate an all-gender leave type behind an assignment', function () {
    $user = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);
    $teacher = TeacherProfile::create([
        'user_id' => $user->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    $leaveType = LeaveType::factory()->create(['applicable_gender' => LeaveApplicability::All]);

    expect($leaveType->requiresAssignment())->toBeFalse()
        ->and(LeaveType::query()->availableFor($teacher)->pluck('id'))->toContain($leaveType->id);
});

it('rejects a teacher applying for a gender-specific leave type that has not been assigned to them', function () {
    $user = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);
    $teacher = TeacherProfile::create([
        'user_id' => $user->id,
        'gender' => Gender::Female,
        'status' => EmploymentStatus::Active,
    ]);

    $leaveType = LeaveType::factory()->create(['applicable_gender' => LeaveApplicability::Female]);

    $this->actingAs($user);

    (new LeaveApplications)->applyForLeave([
        'leave_type_id' => $leaveType->id,
        'from_date' => '2026-08-03',
        'to_date' => '2026-08-03',
        'total_days' => 1,
        'reason' => 'Personal',
    ]);

    expect(LeaveApplication::count())->toBe(0);
});

it('lets a teacher apply for a gender-specific leave type once it is assigned to them', function () {
    $user = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);
    $teacher = TeacherProfile::create([
        'user_id' => $user->id,
        'gender' => Gender::Female,
        'status' => EmploymentStatus::Active,
    ]);

    $leaveType = LeaveType::factory()->create(['applicable_gender' => LeaveApplicability::Female]);
    LeaveTypeAssignment::ensureAssigned($leaveType, $teacher);

    $this->actingAs($user);

    (new LeaveApplications)->applyForLeave([
        'leave_type_id' => $leaveType->id,
        'from_date' => '2026-08-03',
        'to_date' => '2026-08-03',
        'total_days' => 1,
        'reason' => 'Personal',
    ]);

    expect(LeaveApplication::count())->toBe(1);
});

it('auto-assigns a gender-specific leave type when an admin applies on behalf of an unassigned teacher', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $teacherUser = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);
    $teacher = TeacherProfile::create([
        'user_id' => $teacherUser->id,
        'gender' => Gender::Female,
        'status' => EmploymentStatus::Active,
    ]);

    $leaveType = LeaveType::factory()->create(['applicable_gender' => LeaveApplicability::Female]);

    expect($leaveType->isAssignedTo($teacher))->toBeFalse();

    $this->actingAs($admin);

    Livewire::test(CreateLeaveApplication::class)
        ->fillForm([
            'applicant_type' => TeacherProfile::class,
            'applicant_id' => $teacher->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => '2026-08-03',
            'to_date' => '2026-08-03',
            'total_days' => 1,
            'reason' => 'Personal',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(LeaveApplication::count())->toBe(1)
        ->and($leaveType->isAssignedTo($teacher))->toBeTrue();
});
