<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\LeaveApplicability;
use App\Enums\UserType;
use App\Filament\Teacher\Pages\LeaveApplications;
use App\Models\LeaveType;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('logs a leave application submitted by a teacher', function () {
    $teacherUser = User::factory()->create(['name' => 'Shirin Akter', 'user_type' => UserType::Teacher, 'is_active' => true]);
    TeacherProfile::create([
        'user_id' => $teacherUser->id,
        'gender' => Gender::Female,
        'status' => EmploymentStatus::Active,
    ]);

    $leaveType = LeaveType::factory()->create(['name' => 'Casual Leave', 'applicable_gender' => LeaveApplicability::All]);

    test()->actingAs($teacherUser);

    (new LeaveApplications)->applyForLeave([
        'leave_type_id' => $leaveType->id,
        'from_date' => '2026-08-25',
        'to_date' => '2026-08-26',
        'total_days' => 2,
        'reason' => 'Family function',
    ]);

    $activity = Activity::where('log_name', 'leave_application')->where('event', 'created')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($teacherUser->id)
        ->and($activity->description)->toBe('Applied for "Casual Leave" leave for "Shirin Akter" (2026-08-25 to 2026-08-26, 2 day(s)).');
});
