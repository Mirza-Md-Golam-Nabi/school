<?php

use App\Actions\Attendance\ProcessAttendancePunchesAction;
use App\Enums\AttendanceMode;
use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Pages\ManageAttendanceSettings;
use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\Classes;
use App\Models\DeviceUser;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['attendance.auto_enroll' => false]);
    $this->travelTo('2026-09-28 07:50:00');

    AttendanceSetting::current()->update([
        'attendance_mode' => AttendanceMode::Daily,
        'entry_time' => '08:00:00',
        'exit_time' => '14:00:00',
        'late_threshold_minutes' => 15,
    ]);
});

function lateTimePunch(AttendanceDevice $device, Model $person, string $enrollId, string $at): void
{
    DeviceUser::factory()->forPerson($person)->create(['attendance_device_id' => $device->id, 'enroll_id' => $enrollId]);
    AttendancePunch::factory()->create([
        'attendance_device_id' => $device->id,
        'enroll_id' => $enrollId,
        'punched_at' => $at,
        'state' => AttendancePunch::STATE_CHECK_IN,
    ]);
}

it('falls back to entry time plus the grace period when no teacher late time is set', function () {
    $setting = AttendanceSetting::current();

    expect($setting->lateAfterFor(TeacherProfile::class))->toBe('08:15:00')
        ->and($setting->lateMeasuredFromFor(TeacherProfile::class))->toBe('08:00:00');

    $setting->update(['teacher_late_after' => '07:45']);

    expect($setting->lateAfterFor(TeacherProfile::class))->toBe('07:45:00')
        ->and($setting->lateMeasuredFromFor(TeacherProfile::class))->toBe('07:45:00')
        ->and($setting->lateAfterFor(StudentProfile::class))->toBe('08:15:00')
        ->and($setting->lateAfterFor(StaffProfile::class))->toBe('08:15:00');
});

it('marks teachers late by their own late time while students keep the grace period', function () {
    Notification::fake();
    AttendanceSetting::current()->update(['teacher_late_after' => '07:45:00']);

    $device = AttendanceDevice::factory()->create();
    $teacher = TeacherProfile::factory()->create();
    $class = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => 2026,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    lateTimePunch($device, $teacher, '201', '2026-09-28 07:50:00');
    lateTimePunch($device, $student, '101', '2026-09-28 07:50:00');

    app(ProcessAttendancePunchesAction::class)->handle($device);

    expect(Attendance::where('attendable_type', TeacherProfile::class)->sole()->status)->toBe(AttendanceStatus::Late)
        ->and(Attendance::where('attendable_type', StudentProfile::class)->sole()->status)->toBe(AttendanceStatus::Present);
});

it('saves and clears the teacher late time from the settings page', function () {
    Filament::setCurrentPanel('admin');
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    Livewire::actingAs($admin);

    Livewire::test(ManageAttendanceSettings::class)
        ->fillForm(['teacher_late_after' => '08:05'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(AttendanceSetting::current()->teacher_late_after)->toStartWith('08:05');

    Livewire::test(ManageAttendanceSettings::class)
        ->fillForm(['teacher_late_after' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(AttendanceSetting::current()->teacher_late_after)->toBeNull();
});
