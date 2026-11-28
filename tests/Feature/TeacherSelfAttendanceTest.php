<?php

use App\Actions\Attendance\ProcessAttendancePunchesAction;
use App\Enums\AttendanceMode;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\UserType;
use App\Filament\Pages\TeacherAttendance;
use App\Filament\Pages\TeacherAttendanceDetail;
use App\Filament\Teacher\Pages\MyAttendance;
use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\DeviceUser;
use App\Models\TeacherProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['attendance.auto_enroll' => false]);
    $this->travelTo('2026-09-28 07:30:00');

    AttendanceSetting::current()->update([
        'attendance_mode' => AttendanceMode::Daily,
        'entry_time' => '08:00:00',
        'exit_time' => '14:00:00',
        'late_threshold_minutes' => 15,
    ]);

    $this->teacherUser = User::factory()->create(['name' => 'Rahim Sir', 'user_type' => UserType::Teacher, 'is_active' => true]);
    $this->teacher = TeacherProfile::create([
        'user_id' => $this->teacherUser->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
});

function selfAttendanceToday(TeacherProfile $teacher): ?Attendance
{
    return Attendance::where('attendable_type', TeacherProfile::class)
        ->where('attendable_id', $teacher->id)
        ->whereDate('date', today())
        ->first();
}

it('lets a teacher mark themselves present without any permission', function () {
    $this->actingAs($this->teacherUser);

    (new MyAttendance)->markAs('present');

    expect(selfAttendanceToday($this->teacher)->status)->toBe(AttendanceStatus::Present)
        ->and(selfAttendanceToday($this->teacher)->source)->toBe(AttendanceSource::Manual);
});

it('allows only present while the admin has absent and leave switched off', function (string $status) {
    $this->actingAs($this->teacherUser);

    (new MyAttendance)->markAs($status);

    expect(selfAttendanceToday($this->teacher))->toBeNull()
        ->and(MyAttendance::selfMarkableStatuses())->toBe([AttendanceStatus::Present]);
})->with(['absent', 'leave']);

it('allows absent and leave once the admin switches them on', function (string $status) {
    AttendanceSetting::current()->update(['teachers_may_self_mark_absence' => true]);
    $this->actingAs($this->teacherUser);

    (new MyAttendance)->markAs($status);

    expect(selfAttendanceToday($this->teacher)->status->value)->toBe($status);
})->with(['absent', 'leave']);

it('shows only the buttons the teacher may use', function () {
    $this->actingAs($this->teacherUser);
    Filament::setCurrentPanel('teacher');

    Livewire::test(MyAttendance::class)
        ->assertViewHas('buttons', fn (array $buttons): bool => array_column($buttons, 'value') === ['present']);

    AttendanceSetting::current()->update(['teachers_may_self_mark_absence' => true]);

    Livewire::test(MyAttendance::class)
        ->assertViewHas('buttons', fn (array $buttons): bool => array_column($buttons, 'value') === ['present', 'leave', 'absent']);
});

it('never lets a teacher change a record the device made', function () {
    AttendanceSetting::current()->update(['teachers_may_self_mark_absence' => true]);
    Notification::fake();
    Attendance::create([
        'attendable_type' => TeacherProfile::class,
        'attendable_id' => $this->teacher->id,
        'date' => today()->toDateString(),
        'status' => AttendanceStatus::Late,
        'source' => AttendanceSource::Device,
        'entry_time' => '08:40:00',
    ]);

    $this->actingAs($this->teacherUser);
    Filament::setCurrentPanel('teacher');

    Livewire::test(MyAttendance::class)
        ->assertSet('todayFromDevice', true)
        ->assertViewHas('buttons', [])
        ->call('markAs', 'present')
        ->assertNotified();

    expect(selfAttendanceToday($this->teacher)->status)->toBe(AttendanceStatus::Late)
        ->and(selfAttendanceToday($this->teacher)->source)->toBe(AttendanceSource::Device);
});

it('lets a later device punch replace a self-marked present, so a late arrival still shows', function () {
    Notification::fake();
    $device = AttendanceDevice::factory()->create();
    DeviceUser::factory()->forPerson($this->teacher)->create(['attendance_device_id' => $device->id, 'enroll_id' => '101']);

    $this->actingAs($this->teacherUser);
    (new MyAttendance)->markAs('present');

    AttendancePunch::factory()->create([
        'attendance_device_id' => $device->id,
        'enroll_id' => '101',
        'punched_at' => '2026-09-28 08:40:00',
        'state' => AttendancePunch::STATE_CHECK_IN,
    ]);
    app(ProcessAttendancePunchesAction::class)->handle($device);

    expect(selfAttendanceToday($this->teacher)->status)->toBe(AttendanceStatus::Late)
        ->and(selfAttendanceToday($this->teacher)->source)->toBe(AttendanceSource::Device);
});

it('shows admins who entered a manual record, and nothing for a device record', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $otherTeacher = TeacherProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
    $deviceTeacher = TeacherProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    $this->actingAs($this->teacherUser);
    (new MyAttendance)->markAs('present');

    foreach ([[$otherTeacher, AttendanceSource::Manual, $admin->id], [$deviceTeacher, AttendanceSource::Device, null]] as [$teacher, $source, $markedBy]) {
        Attendance::create([
            'attendable_type' => TeacherProfile::class,
            'attendable_id' => $teacher->id,
            'date' => today()->toDateString(),
            'status' => AttendanceStatus::Present,
            'source' => $source,
            'marked_by' => $markedBy,
        ]);
    }

    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    $labels = Livewire::test(TeacherAttendance::class)->instance()->getManualEntryLabels();

    expect($labels->get($this->teacher->id))->toBe('নিজে (ম্যানুয়াল)')
        ->and($labels->get($otherTeacher->id))->toBe('অ্যাডমিন (ম্যানুয়াল)')
        ->and($labels->has($deviceTeacher->id))->toBeFalse();

    Livewire::test(TeacherAttendanceDetail::class, ['teacherId' => $this->teacher->id])
        ->assertSee('নিজে (ম্যানুয়াল)');
});
