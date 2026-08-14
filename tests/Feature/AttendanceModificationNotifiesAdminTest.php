<?php

use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Pages\AttendanceModificationDetails;
use App\Filament\Pages\MarkStudentAttendance as AdminMarkStudentAttendance;
use App\Filament\Teacher\Pages\MarkStudentAttendance as TeacherMarkStudentAttendance;
use App\Models\Attendance;
use App\Models\AttendanceStatusChange;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

dataset('attendanceModificationPanels', [
    'admin panel' => [AdminMarkStudentAttendance::class],
    'teacher panel' => [TeacherMarkStudentAttendance::class],
]);

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

function actingAsAttendanceModificationMarker(string $componentClass): User
{
    $userType = $componentClass === AdminMarkStudentAttendance::class ? UserType::Admin : UserType::Teacher;
    $marker = grantSuperAdmin(User::factory()->create(['user_type' => $userType, 'is_active' => true]));
    test()->actingAs($marker);

    return $marker;
}

function createAttendanceModificationAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $admin = User::factory()->create(['user_type' => UserType::Admin]);
    $admin->assignRole('admin');

    return $admin;
}

it('notifies admins when attendance is created for the first time on a past date', function (string $componentClass) {
    $admin = createAttendanceModificationAdmin();
    $class = createAttendanceModificationTestClass();
    $student = createAttendanceModificationTestStudent($class, 1);
    actingAsAttendanceModificationMarker($componentClass);

    $pastDate = today()->subDays(3)->toDateString();

    Livewire::test($componentClass)
        ->set('classId', $class->id)
        ->set('date', $pastDate)
        ->set('presentIds', [(string) $student->id])
        ->call('save');

    expect(AttendanceStatusChange::count())->toBe(1);

    $change = AttendanceStatusChange::first();

    expect($change->old_status)->toBeNull()
        ->and($change->new_status)->toBe(AttendanceStatus::Present)
        ->and($admin->notifications()->count())->toBe(1);
})->with('attendanceModificationPanels');

it('does not notify admins when re-saving a past date with unchanged statuses', function (string $componentClass) {
    $admin = createAttendanceModificationAdmin();
    $class = createAttendanceModificationTestClass();
    $student = createAttendanceModificationTestStudent($class, 1);
    actingAsAttendanceModificationMarker($componentClass);

    $pastDate = today()->subDays(3)->toDateString();
    seedAttendanceModificationTestRecord($student, $class, $pastDate, AttendanceStatus::Present);

    Livewire::test($componentClass)
        ->set('classId', $class->id)
        ->set('date', $pastDate)
        ->set('presentIds', [(string) $student->id])
        ->call('save');

    expect(AttendanceStatusChange::count())->toBe(0)
        ->and($admin->notifications()->count())->toBe(0);
})->with('attendanceModificationPanels');

it('notifies admins with the correct details when a past date attendance status is changed', function (string $componentClass) {
    $admin = createAttendanceModificationAdmin();
    $class = createAttendanceModificationTestClass();
    $student = createAttendanceModificationTestStudent($class, 1);
    $otherStudent = createAttendanceModificationTestStudent($class, 2);
    $marker = actingAsAttendanceModificationMarker($componentClass);

    $pastDate = today()->subDays(3)->toDateString();
    seedAttendanceModificationTestRecord($student, $class, $pastDate, AttendanceStatus::Present);
    seedAttendanceModificationTestRecord($otherStudent, $class, $pastDate, AttendanceStatus::Absent);

    // Flip $student from present to absent, keep $otherStudent as absent (unchanged).
    Livewire::test($componentClass)
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
        ->and($change->changed_by)->toBe($marker->id);

    expect($admin->notifications()->count())->toBe(1);

    $notification = $admin->notifications()->first();

    expect($notification->data['title'])->toBe('Attendance Flagged for Review')
        ->and($notification->data['body'])->toContain('1 student')
        ->and($notification->data['body'])->toContain($class->name);
})->with('attendanceModificationPanels');

it("does not notify admins when modifying today's attendance", function (string $componentClass) {
    $admin = createAttendanceModificationAdmin();
    $class = createAttendanceModificationTestClass();
    $student = createAttendanceModificationTestStudent($class, 1);
    actingAsAttendanceModificationMarker($componentClass);

    $today = today()->toDateString();
    seedAttendanceModificationTestRecord($student, $class, $today, AttendanceStatus::Present);

    Livewire::test($componentClass)
        ->set('classId', $class->id)
        ->set('date', $today)
        ->set('presentIds', [])
        ->call('save');

    expect(AttendanceStatusChange::count())->toBe(0)
        ->and($admin->notifications()->count())->toBe(0);
})->with('attendanceModificationPanels');

it('does not notify admins when attendance is created for the first time on today', function (string $componentClass) {
    $admin = createAttendanceModificationAdmin();
    $class = createAttendanceModificationTestClass();
    $student = createAttendanceModificationTestStudent($class, 1);
    actingAsAttendanceModificationMarker($componentClass);

    $today = today()->toDateString();

    Livewire::test($componentClass)
        ->set('classId', $class->id)
        ->set('date', $today)
        ->set('presentIds', [(string) $student->id])
        ->call('save');

    expect(AttendanceStatusChange::count())->toBe(0)
        ->and($admin->notifications()->count())->toBe(0);
})->with('attendanceModificationPanels');

it('notifies admins when attendance is created for the first time on a future date', function (string $componentClass) {
    $admin = createAttendanceModificationAdmin();
    $class = createAttendanceModificationTestClass();
    $student = createAttendanceModificationTestStudent($class, 1);
    actingAsAttendanceModificationMarker($componentClass);

    $futureDate = today()->addDays(5)->toDateString();

    Livewire::test($componentClass)
        ->set('classId', $class->id)
        ->set('date', $futureDate)
        ->set('presentIds', [(string) $student->id])
        ->call('save');

    expect(AttendanceStatusChange::count())->toBe(1);

    $change = AttendanceStatusChange::first();

    expect($change->old_status)->toBeNull()
        ->and($change->new_status)->toBe(AttendanceStatus::Present)
        ->and($admin->notifications()->count())->toBe(1);
})->with('attendanceModificationPanels');

it('notifies admins when a future date attendance status is changed', function (string $componentClass) {
    $admin = createAttendanceModificationAdmin();
    $class = createAttendanceModificationTestClass();
    $student = createAttendanceModificationTestStudent($class, 1);
    actingAsAttendanceModificationMarker($componentClass);

    $futureDate = today()->addDays(5)->toDateString();
    seedAttendanceModificationTestRecord($student, $class, $futureDate, AttendanceStatus::Present);

    Livewire::test($componentClass)
        ->set('classId', $class->id)
        ->set('date', $futureDate)
        ->set('presentIds', [])
        ->call('save');

    expect(AttendanceStatusChange::count())->toBe(1)
        ->and($admin->notifications()->count())->toBe(1);
})->with('attendanceModificationPanels');

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

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin]));

    Livewire::actingAs($admin)
        ->test(AttendanceModificationDetails::class, ['batchId' => $batchId])
        ->assertSee($student->user->name)
        ->assertSee($teacher->name);
});
