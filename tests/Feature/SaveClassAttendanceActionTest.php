<?php

use App\Actions\Attendance\SaveClassAttendanceAction;
use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Attendance;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\assertDatabaseCount;

uses(RefreshDatabase::class);

/**
 * Regression test: a `date` cast without an explicit format used to write
 * "Y-m-d H:i:s" to the database while updateOrCreate()'s raw where() clause
 * queried the plain "Y-m-d" string. The mismatch meant the existing row was
 * never found, so every re-save inserted a brand new duplicate row instead
 * of updating the one already there.
 */
it('updates the existing attendance row on re-save instead of creating a duplicate', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);

    $student = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

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

    assertDatabaseCount('attendances', 1);

    // Re-save the same day, now marking the student absent.
    app(SaveClassAttendanceAction::class)->handle(
        classId: $class->id,
        class: $class,
        date: $date,
        students: collect([$student]),
        presentIds: [],
        markedBy: $admin->id,
        markedByName: $admin->name,
    );

    assertDatabaseCount('attendances', 1);

    $attendance = Attendance::first();

    expect($attendance->status)->toBe(AttendanceStatus::Absent)
        ->and($attendance->getRawOriginal('date'))->toBe($date);
});

/**
 * Regression test: SaveClassAttendanceAction always stamped entry_time with
 * now() for present students, so resaving the whole class (e.g. to correct
 * one student) marked every already-present student dirty, logging an
 * "updated" activity entry for the entire class instead of just the one
 * student whose status actually changed.
 */
it('does not log an update for students whose attendance did not actually change', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 6', 'order' => 6, 'is_active' => true]);

    $unchangedStudent = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $correctedStudent = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 2,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $date = now()->toDateString();

    app(SaveClassAttendanceAction::class)->handle(
        classId: $class->id,
        class: $class,
        date: $date,
        students: collect([$unchangedStudent, $correctedStudent]),
        presentIds: [(string) $unchangedStudent->id, (string) $correctedStudent->id],
        markedBy: $admin->id,
        markedByName: $admin->name,
    );

    Activity::query()->delete();

    $unchangedAttendance = Attendance::where('attendable_id', $unchangedStudent->id)->first();
    $originalEntryTime = $unchangedAttendance->entry_time;

    // Advance the clock so a buggy re-stamp of entry_time would be provably
    // different from the original, instead of coincidentally matching it.
    test()->travel(5)->minutes();

    // Re-save the whole class, correcting only the second student to absent.
    app(SaveClassAttendanceAction::class)->handle(
        classId: $class->id,
        class: $class,
        date: $date,
        students: collect([$unchangedStudent, $correctedStudent]),
        presentIds: [(string) $unchangedStudent->id],
        markedBy: $admin->id,
        markedByName: $admin->name,
    );

    expect(Activity::where('log_name', 'student_attendance')->where('event', 'updated')->count())->toBe(1);

    $correctedAttendance = Attendance::where('attendable_id', $correctedStudent->id)->first();
    $updatedActivity = Activity::where('log_name', 'student_attendance')->where('event', 'updated')->first();
    expect($updatedActivity->subject_id)->toBe($correctedAttendance->id);

    expect($unchangedAttendance->fresh()->entry_time)->toBe($originalEntryTime);
});
