<?php

use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Pages\AttendanceModificationDetails;
use App\Filament\Teacher\Pages\MarkStudentAttendance;
use App\Models\Attendance;
use App\Models\AttendanceStatusChange;
use App\Models\Classes;
use App\Models\PublicHoliday;
use App\Models\SchoolSetting;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createAttendanceModificationTestClass(): Classes
{
    return Classes::create(['name' => 'Class '.str()->random(4), 'order' => 1, 'is_active' => true]);
}

function createAttendanceModificationTestStudent(Classes $class, int $rollNo): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create()->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

/**
 * Bypasses Eloquent's `date` cast, which appends a spurious time
 * component under SQLite and breaks exact-string date lookups.
 */
function seedAttendanceModificationTestRecord(StudentProfile $student, Classes $class, string $date, AttendanceStatus $status): void
{
    Attendance::insert([
        'attendable_type' => StudentProfile::class,
        'attendable_id' => $student->id,
        'date' => $date,
        'class_id' => $class->id,
        'status' => $status->value,
        'source' => 'manual',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function actingAsAttendanceModificationTeacher(): User
{
    $teacher = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);
    test()->actingAs($teacher);

    return $teacher;
}

function createAttendanceModificationAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $admin = User::factory()->create(['user_type' => UserType::Admin]);
    $admin->assignRole('admin');

    return $admin;
}

it('does not notify admins when a past working day is marked for the first time', function () {
    SchoolSetting::set('weekend_days', json_encode([]));

    $admin = createAttendanceModificationAdmin();
    $class = createAttendanceModificationTestClass();
    $student = createAttendanceModificationTestStudent($class, 1);
    actingAsAttendanceModificationTeacher();

    $pastDate = today()->subDays(3)->toDateString();

    Livewire::test(MarkStudentAttendance::class)
        ->set('classId', $class->id)
        ->set('date', $pastDate)
        ->set('presentIds', [(string) $student->id])
        ->call('save');

    expect(AttendanceStatusChange::count())->toBe(0)
        ->and($admin->notifications()->count())->toBe(0);
});

it('does not notify admins when re-saving a past date with unchanged statuses', function () {
    $admin = createAttendanceModificationAdmin();
    $class = createAttendanceModificationTestClass();
    $student = createAttendanceModificationTestStudent($class, 1);
    actingAsAttendanceModificationTeacher();

    $pastDate = today()->subDays(3)->toDateString();
    seedAttendanceModificationTestRecord($student, $class, $pastDate, AttendanceStatus::Present);

    Livewire::test(MarkStudentAttendance::class)
        ->set('classId', $class->id)
        ->set('date', $pastDate)
        ->set('presentIds', [(string) $student->id])
        ->call('save');

    expect(AttendanceStatusChange::count())->toBe(0)
        ->and($admin->notifications()->count())->toBe(0);
});

it('notifies admins with the correct details when a past date attendance status is changed', function () {
    $admin = createAttendanceModificationAdmin();
    $class = createAttendanceModificationTestClass();
    $student = createAttendanceModificationTestStudent($class, 1);
    $otherStudent = createAttendanceModificationTestStudent($class, 2);
    $teacher = actingAsAttendanceModificationTeacher();

    $pastDate = today()->subDays(3)->toDateString();
    seedAttendanceModificationTestRecord($student, $class, $pastDate, AttendanceStatus::Present);
    seedAttendanceModificationTestRecord($otherStudent, $class, $pastDate, AttendanceStatus::Absent);

    // Flip $student from present to absent, keep $otherStudent as absent (unchanged).
    Livewire::test(MarkStudentAttendance::class)
        ->set('classId', $class->id)
        ->set('date', $pastDate)
        ->set('presentIds', [])
        ->call('save');

    expect(AttendanceStatusChange::count())->toBe(1);

    $change = AttendanceStatusChange::first();

    expect($change->student_profile_id)->toBe($student->id)
        ->and($change->class_id)->toBe($class->id)
        ->and($change->old_status)->toBe(AttendanceStatus::Present)
        ->and($change->new_status)->toBe(AttendanceStatus::Absent)
        ->and($change->changed_by)->toBe($teacher->id);

    expect($admin->notifications()->count())->toBe(1);

    $notification = $admin->notifications()->first();

    expect($notification->data['title'])->toBe('Attendance Flagged for Review')
        ->and($notification->data['body'])->toContain('1 student')
        ->and($notification->data['body'])->toContain($class->name);
});

it("does not notify admins when modifying today's attendance on a working day", function () {
    // Explicitly clear weekend days so this test is deterministic regardless of which
    // real-world weekday it happens to run on.
    SchoolSetting::set('weekend_days', json_encode([]));

    $admin = createAttendanceModificationAdmin();
    $class = createAttendanceModificationTestClass();
    $student = createAttendanceModificationTestStudent($class, 1);
    actingAsAttendanceModificationTeacher();

    $today = today()->toDateString();
    seedAttendanceModificationTestRecord($student, $class, $today, AttendanceStatus::Present);

    Livewire::test(MarkStudentAttendance::class)
        ->set('classId', $class->id)
        ->set('date', $today)
        ->set('presentIds', [])
        ->call('save');

    expect(AttendanceStatusChange::count())->toBe(0)
        ->and($admin->notifications()->count())->toBe(0);
});

it('notifies admins when a non-past attendance status is changed on a weekend day', function () {
    SchoolSetting::set('weekend_days', json_encode(['friday']));

    $admin = createAttendanceModificationAdmin();
    $class = createAttendanceModificationTestClass();
    $student = createAttendanceModificationTestStudent($class, 1);
    actingAsAttendanceModificationTeacher();

    $friday = Carbon::today()->next(Carbon::FRIDAY)->toDateString();
    seedAttendanceModificationTestRecord($student, $class, $friday, AttendanceStatus::Present);

    Livewire::test(MarkStudentAttendance::class)
        ->set('classId', $class->id)
        ->set('date', $friday)
        ->set('presentIds', [])
        ->call('save');

    expect(AttendanceStatusChange::count())->toBe(1)
        ->and($admin->notifications()->count())->toBe(1);
});

it('notifies admins when a non-past attendance status is changed on a public holiday', function () {
    SchoolSetting::set('weekend_days', json_encode([]));

    $admin = createAttendanceModificationAdmin();
    $class = createAttendanceModificationTestClass();
    $student = createAttendanceModificationTestStudent($class, 1);
    actingAsAttendanceModificationTeacher();

    $holidayDate = today()->addDays(10);

    PublicHoliday::create([
        'name' => 'Test Holiday',
        'start_date' => $holidayDate,
        'is_recurring' => false,
    ]);

    seedAttendanceModificationTestRecord($student, $class, $holidayDate->toDateString(), AttendanceStatus::Present);

    Livewire::test(MarkStudentAttendance::class)
        ->set('classId', $class->id)
        ->set('date', $holidayDate->toDateString())
        ->set('presentIds', [])
        ->call('save');

    expect(AttendanceStatusChange::count())->toBe(1)
        ->and($admin->notifications()->count())->toBe(1);
});

it('notifies admins when attendance is created for the first time on a weekend day', function () {
    // Attendance is never normally taken on a weekend, so there's no pre-existing
    // record to "change" — the mere act of recording it at all must be flagged.
    SchoolSetting::set('weekend_days', json_encode(['friday']));

    $admin = createAttendanceModificationAdmin();
    $class = createAttendanceModificationTestClass();
    $student = createAttendanceModificationTestStudent($class, 1);
    actingAsAttendanceModificationTeacher();

    $friday = Carbon::today()->next(Carbon::FRIDAY)->toDateString();

    Livewire::test(MarkStudentAttendance::class)
        ->set('classId', $class->id)
        ->set('date', $friday)
        ->set('presentIds', [(string) $student->id])
        ->call('save');

    expect(AttendanceStatusChange::count())->toBe(1);

    $change = AttendanceStatusChange::first();

    expect($change->old_status)->toBeNull()
        ->and($change->new_status)->toBe(AttendanceStatus::Present)
        ->and($admin->notifications()->count())->toBe(1);
});

it('notifies admins when attendance is created for the first time on a public holiday', function () {
    SchoolSetting::set('weekend_days', json_encode([]));

    $admin = createAttendanceModificationAdmin();
    $class = createAttendanceModificationTestClass();
    $student = createAttendanceModificationTestStudent($class, 1);
    actingAsAttendanceModificationTeacher();

    $holidayDate = today()->addDays(10);

    PublicHoliday::create([
        'name' => 'Test Holiday',
        'start_date' => $holidayDate,
        'is_recurring' => false,
    ]);

    Livewire::test(MarkStudentAttendance::class)
        ->set('classId', $class->id)
        ->set('date', $holidayDate->toDateString())
        ->set('presentIds', [(string) $student->id])
        ->call('save');

    expect(AttendanceStatusChange::count())->toBe(1);

    $change = AttendanceStatusChange::first();

    expect($change->old_status)->toBeNull()
        ->and($change->new_status)->toBe(AttendanceStatus::Present)
        ->and($admin->notifications()->count())->toBe(1);
});

it('does not notify admins when attendance is created for the first time on an ordinary working day', function () {
    SchoolSetting::set('weekend_days', json_encode([]));

    $admin = createAttendanceModificationAdmin();
    $class = createAttendanceModificationTestClass();
    $student = createAttendanceModificationTestStudent($class, 1);
    actingAsAttendanceModificationTeacher();

    $today = today()->toDateString();

    Livewire::test(MarkStudentAttendance::class)
        ->set('classId', $class->id)
        ->set('date', $today)
        ->set('presentIds', [(string) $student->id])
        ->call('save');

    expect(AttendanceStatusChange::count())->toBe(0)
        ->and($admin->notifications()->count())->toBe(0);
});

it('does not notify admins when a non-past, non-weekend, non-holiday attendance status is changed', function () {
    SchoolSetting::set('weekend_days', json_encode([]));

    $admin = createAttendanceModificationAdmin();
    $class = createAttendanceModificationTestClass();
    $student = createAttendanceModificationTestStudent($class, 1);
    actingAsAttendanceModificationTeacher();

    $futureDate = today()->addDays(5)->toDateString();
    seedAttendanceModificationTestRecord($student, $class, $futureDate, AttendanceStatus::Present);

    Livewire::test(MarkStudentAttendance::class)
        ->set('classId', $class->id)
        ->set('date', $futureDate)
        ->set('presentIds', [])
        ->call('save');

    expect(AttendanceStatusChange::count())->toBe(0)
        ->and($admin->notifications()->count())->toBe(0);
});

it('renders the changed students for a given batch on the details page', function () {
    $class = createAttendanceModificationTestClass();
    $student = createAttendanceModificationTestStudent($class, 1);
    $teacher = User::factory()->create(['user_type' => UserType::Teacher]);

    $attendance = Attendance::create([
        'attendable_type' => StudentProfile::class,
        'attendable_id' => $student->id,
        'date' => today()->subDays(2),
        'class_id' => $class->id,
        'status' => AttendanceStatus::Absent,
    ]);

    $batchId = (string) Str::uuid();

    AttendanceStatusChange::create([
        'attendance_id' => $attendance->id,
        'class_id' => $class->id,
        'student_profile_id' => $student->id,
        'date' => $attendance->date,
        'old_status' => AttendanceStatus::Present,
        'new_status' => AttendanceStatus::Absent,
        'changed_by' => $teacher->id,
        'batch_id' => $batchId,
    ]);

    $admin = User::factory()->create(['user_type' => UserType::Admin]);

    Livewire::actingAs($admin)
        ->test(AttendanceModificationDetails::class, ['batchId' => $batchId])
        ->assertSee($student->user->name)
        ->assertSee($teacher->name);
});
