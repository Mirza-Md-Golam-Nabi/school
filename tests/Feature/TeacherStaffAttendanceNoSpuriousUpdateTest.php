<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\UserType;
use App\Filament\Pages\StaffAttendance;
use App\Filament\Pages\TeacherAttendance;
use App\Models\Attendance;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/**
 * Same fix as SaveClassAttendanceAction: entry_time must not be re-stamped
 * for a teacher/staff member whose presence didn't actually change, or
 * resaving the whole roster to correct one person logs everyone as updated.
 */
it('does not log an update for a teacher whose attendance did not actually change', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $unchanged = TeacherProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    $corrected = TeacherProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    $date = now()->toDateString();

    $page = new TeacherAttendance;
    $page->date = $date;
    $page->presentIds = [(string) $unchanged->id, (string) $corrected->id];
    $page->save();

    Activity::query()->delete();
    $originalEntryTime = Attendance::where('attendable_id', $unchanged->id)->first()->entry_time;

    test()->travel(5)->minutes();

    $page = new TeacherAttendance;
    $page->date = $date;
    $page->presentIds = [(string) $unchanged->id];
    $page->save();

    expect(Activity::where('log_name', 'teacher_attendance')->where('event', 'updated')->count())->toBe(1);

    $correctedAttendance = Attendance::where('attendable_id', $corrected->id)->first();
    $updatedActivity = Activity::where('log_name', 'teacher_attendance')->where('event', 'updated')->first();
    expect($updatedActivity->subject_id)->toBe($correctedAttendance->id);

    expect(Attendance::where('attendable_id', $unchanged->id)->first()->entry_time)->toBe($originalEntryTime);
});

it('does not log an update for a staff member whose attendance did not actually change', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $unchanged = StaffProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Staff, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    $corrected = StaffProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Staff, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    $date = now()->toDateString();

    $page = new StaffAttendance;
    $page->date = $date;
    $page->presentIds = [(string) $unchanged->id, (string) $corrected->id];
    $page->save();

    Activity::query()->delete();
    $originalEntryTime = Attendance::where('attendable_id', $unchanged->id)->first()->entry_time;

    test()->travel(5)->minutes();

    $page = new StaffAttendance;
    $page->date = $date;
    $page->presentIds = [(string) $unchanged->id];
    $page->save();

    expect(Activity::where('log_name', 'staff_attendance')->where('event', 'updated')->count())->toBe(1);

    $correctedAttendance = Attendance::where('attendable_id', $corrected->id)->first();
    $updatedActivity = Activity::where('log_name', 'staff_attendance')->where('event', 'updated')->first();
    expect($updatedActivity->subject_id)->toBe($correctedAttendance->id);

    expect(Attendance::where('attendable_id', $unchanged->id)->first()->entry_time)->toBe($originalEntryTime);
});
