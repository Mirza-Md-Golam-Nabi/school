<?php

use App\Actions\Attendance\SaveClassAttendanceAction;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\StudentAttendanceMarkedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function createAttendanceNotificationTestStudent(Classes $class, int $rollNo): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it('notifies the student when marked present for the first time', function () {
    Notification::fake();

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);
    $student = createAttendanceNotificationTestStudent($class, 1);
    $date = now()->toDateString();

    app(SaveClassAttendanceAction::class)->handle(
        classId: $class->id,
        class: $class,
        date: $date,
        students: collect([$student]),
        presentIds: [(string) $student->id],
        markedBy: $admin->id,
        markedByName: $admin->name,
    );

    Notification::assertSentTo(
        $student->user,
        StudentAttendanceMarkedNotification::class,
        fn (StudentAttendanceMarkedNotification $n) => $n->attendance->status->value === 'present',
    );
});

it('notifies the student when marked absent for the first time', function () {
    Notification::fake();

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);
    $student = createAttendanceNotificationTestStudent($class, 1);
    $date = now()->toDateString();

    app(SaveClassAttendanceAction::class)->handle(
        classId: $class->id,
        class: $class,
        date: $date,
        students: collect([$student]),
        presentIds: [],
        markedBy: $admin->id,
        markedByName: $admin->name,
    );

    Notification::assertSentTo(
        $student->user,
        StudentAttendanceMarkedNotification::class,
        fn (StudentAttendanceMarkedNotification $n) => $n->attendance->status->value === 'absent',
    );
});

it('notifies the student again when their attendance status actually changes', function () {
    Notification::fake();

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);
    $student = createAttendanceNotificationTestStudent($class, 1);
    $date = now()->toDateString();

    app(SaveClassAttendanceAction::class)->handle(
        classId: $class->id, class: $class, date: $date,
        students: collect([$student]), presentIds: [(string) $student->id],
        markedBy: $admin->id, markedByName: $admin->name,
    );

    // Correct to absent.
    app(SaveClassAttendanceAction::class)->handle(
        classId: $class->id, class: $class, date: $date,
        students: collect([$student]), presentIds: [],
        markedBy: $admin->id, markedByName: $admin->name,
    );

    Notification::assertSentToTimes($student->user, StudentAttendanceMarkedNotification::class, 2);
});

it('does not notify the student again when attendance is resaved with the same status', function () {
    Notification::fake();

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);
    $unchanged = createAttendanceNotificationTestStudent($class, 1);
    $corrected = createAttendanceNotificationTestStudent($class, 2);
    $date = now()->toDateString();

    app(SaveClassAttendanceAction::class)->handle(
        classId: $class->id, class: $class, date: $date,
        students: collect([$unchanged, $corrected]),
        presentIds: [(string) $unchanged->id, (string) $corrected->id],
        markedBy: $admin->id, markedByName: $admin->name,
    );

    // Re-save the whole class, correcting only the second student.
    app(SaveClassAttendanceAction::class)->handle(
        classId: $class->id, class: $class, date: $date,
        students: collect([$unchanged, $corrected]),
        presentIds: [(string) $unchanged->id],
        markedBy: $admin->id, markedByName: $admin->name,
    );

    Notification::assertSentToTimes($unchanged->user, StudentAttendanceMarkedNotification::class, 1);
    Notification::assertSentToTimes($corrected->user, StudentAttendanceMarkedNotification::class, 2);
});
