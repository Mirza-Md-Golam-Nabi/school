<?php

use App\Actions\Attendance\SaveClassAttendanceAction;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Attendance;
use App\Models\Classes;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('logs student attendance with the class, student, and status in the description', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);

    $presentUser = User::factory()->create(['name' => 'Rahim Uddin', 'user_type' => UserType::Student, 'is_active' => true]);
    $presentStudent = StudentProfile::create([
        'user_id' => $presentUser->id,
        'roll_no' => 3,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $absentUser = User::factory()->create(['name' => 'Karim Miah', 'user_type' => UserType::Student, 'is_active' => true]);
    $absentStudent = StudentProfile::create([
        'user_id' => $absentUser->id,
        'roll_no' => 4,
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
        students: collect([$presentStudent, $absentStudent]),
        presentIds: [(string) $presentStudent->id],
        markedBy: $admin->id,
        markedByName: $admin->name,
    );

    $presentActivity = Activity::where('log_name', 'student_attendance')
        ->where('event', 'created')
        ->get()
        ->first(fn (Activity $activity) => $activity->properties->get('attributes')['attendable_id'] === $presentStudent->id);

    expect($presentActivity)->not->toBeNull()
        ->and($presentActivity->description)->toBe('Created attendance for "Rahim Uddin (Roll: 3)" in "Class 5" on '.$date.' (Present).');

    expect($presentActivity->properties->get('attributes'))
        ->toMatchArray([
            'class_id' => $class->id,
            'class_id_label' => 'Class 5',
        ]);

    $absentActivity = Activity::where('log_name', 'student_attendance')
        ->where('event', 'created')
        ->get()
        ->first(fn (Activity $activity) => $activity->properties->get('attributes')['attendable_id'] === $absentStudent->id);

    expect($absentActivity)->not->toBeNull()
        ->and($absentActivity->description)->toBe('Created attendance for "Karim Miah (Roll: 4)" in "Class 5" on '.$date.' (Absent).');
});

it('logs teacher attendance without a dangling class reference, since teachers have no class_id', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    test()->actingAs($admin);

    $teacher = TeacherProfile::create([
        'user_id' => User::factory()->create(['name' => 'Jamal Sir', 'user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    $date = now()->toDateString();

    Attendance::updateOrCreate(
        [
            'attendable_type' => TeacherProfile::class,
            'attendable_id' => $teacher->id,
            'date' => $date,
            'class_id' => null,
            'subject_id' => null,
        ],
        [
            'status' => AttendanceStatus::Present,
            'source' => AttendanceSource::Manual,
            'marked_by' => $admin->id,
        ]
    );

    $activity = Activity::where('log_name', 'teacher_attendance')->where('event', 'created')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Created attendance for "Jamal Sir" on '.$date.' (Present).');
});

it('logs staff attendance without a dangling class reference, since staff have no class_id', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    test()->actingAs($admin);

    $staff = StaffProfile::create([
        'user_id' => User::factory()->create(['name' => 'Salma Begum', 'user_type' => UserType::Staff, 'is_active' => true])->id,
        'gender' => Gender::Female,
        'status' => EmploymentStatus::Active,
    ]);

    $date = now()->toDateString();

    Attendance::updateOrCreate(
        [
            'attendable_type' => StaffProfile::class,
            'attendable_id' => $staff->id,
            'date' => $date,
            'class_id' => null,
            'subject_id' => null,
        ],
        [
            'status' => AttendanceStatus::Absent,
            'source' => AttendanceSource::Manual,
            'marked_by' => $admin->id,
        ]
    );

    $activity = Activity::where('log_name', 'staff_attendance')->where('event', 'created')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Created attendance for "Salma Begum" on '.$date.' (Absent).');
});
