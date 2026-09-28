<?php

use App\Enums\AttendanceMode;
use App\Enums\AttendanceSource;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\Classes;
use App\Models\DeviceUser;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['attendance.auto_enroll' => false]);
});

function punchApiDevice(string $token = 'secret-device-token'): AttendanceDevice
{
    return AttendanceDevice::factory()->withToken($token)->create();
}

it('rejects requests without a device token', function () {
    $this->postJson(route('api.device.punches.store'), [
        'punches' => [['enroll_id' => '1', 'punched_at' => '2026-09-28 08:00:00']],
    ])->assertUnauthorized();

    expect(AttendancePunch::count())->toBe(0);
});

it('rejects an unknown device token', function () {
    punchApiDevice();

    $this->withToken('wrong-token')
        ->postJson(route('api.device.punches.store'), [
            'punches' => [['enroll_id' => '1', 'punched_at' => '2026-09-28 08:00:00']],
        ])
        ->assertUnauthorized();
});

it('rejects the token of an inactive device', function () {
    AttendanceDevice::factory()->withToken('inactive-token')->inactive()->create();

    $this->withToken('inactive-token')
        ->postJson(route('api.device.punches.store'), [
            'punches' => [['enroll_id' => '1', 'punched_at' => '2026-09-28 08:00:00']],
        ])
        ->assertUnauthorized();
});

it('stores a batch of punches and stamps the device sync time', function () {
    $device = punchApiDevice();

    $this->withToken('secret-device-token')
        ->postJson(route('api.device.punches.store'), [
            'punches' => [
                ['enroll_id' => '1', 'punched_at' => '2026-09-28 08:00:00', 'verify_type' => 1, 'state' => 0],
                ['enroll_id' => '2', 'punched_at' => '2026-09-28 08:05:00'],
            ],
        ])
        ->assertSuccessful()
        ->assertJson(['received' => 2, 'created' => 2]);

    expect(AttendancePunch::where('attendance_device_id', $device->id)->count())->toBe(2)
        ->and($device->fresh()->last_synced_at)->not->toBeNull();
});

it('ignores punches it has already stored so pull and live capture can overlap', function () {
    punchApiDevice();

    $payload = ['punches' => [['enroll_id' => '1', 'punched_at' => '2026-09-28 08:00:00']]];

    $this->withToken('secret-device-token')->postJson(route('api.device.punches.store'), $payload)
        ->assertSuccessful()
        ->assertJson(['created' => 1]);

    $this->withToken('secret-device-token')->postJson(route('api.device.punches.store'), $payload)
        ->assertSuccessful()
        ->assertJson(['received' => 1, 'created' => 0]);

    expect(AttendancePunch::count())->toBe(1);
});

it('accepts numeric enroll ids sent as JSON integers', function () {
    punchApiDevice();

    $this->withToken('secret-device-token')
        ->postJson(route('api.device.punches.store'), [
            'punches' => [['enroll_id' => 12, 'punched_at' => '2026-09-28 08:00:00']],
        ])
        ->assertSuccessful();

    expect(AttendancePunch::first()->enroll_id)->toBe('12');
});

it('validates the punch payload', function (array $payload, string $invalidKey) {
    punchApiDevice();

    $this->withToken('secret-device-token')
        ->postJson(route('api.device.punches.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($invalidKey);
})->with([
    'missing punches' => [[], 'punches'],
    'empty punches' => [['punches' => []], 'punches'],
    'missing enroll id' => [['punches' => [['punched_at' => '2026-09-28 08:00:00']]], 'punches.0.enroll_id'],
    'missing punched_at' => [['punches' => [['enroll_id' => '1']]], 'punches.0.punched_at'],
    'invalid punched_at' => [['punches' => [['enroll_id' => '1', 'punched_at' => 'not-a-date']]], 'punches.0.punched_at'],
    'enroll id too long' => [['punches' => [['enroll_id' => str_repeat('9', 51), 'punched_at' => '2026-09-28 08:00:00']]], 'punches.0.enroll_id'],
]);

it('caps a single batch at 1000 punches', function () {
    punchApiDevice();

    $punches = array_fill(0, 1001, ['enroll_id' => '1', 'punched_at' => '2026-09-28 08:00:00']);

    $this->withToken('secret-device-token')
        ->postJson(route('api.device.punches.store'), ['punches' => $punches])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('punches');
});

it('turns a mapped student punch into an attendance row end to end', function () {
    Notification::fake();
    $this->travelTo('2026-09-28 08:05:00');
    AttendanceSetting::current()->update(['attendance_mode' => AttendanceMode::Daily]);

    $device = punchApiDevice();
    $class = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => 2026,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
    DeviceUser::factory()->forPerson($student)->create(['attendance_device_id' => $device->id, 'enroll_id' => '101']);

    $this->withToken('secret-device-token')
        ->postJson(route('api.device.punches.store'), [
            'punches' => [['enroll_id' => '101', 'punched_at' => '2026-09-28 08:02:00']],
        ])
        ->assertSuccessful();

    $attendance = Attendance::where('attendable_id', $student->id)->first();

    expect($attendance)->not->toBeNull()
        ->and($attendance->source)->toBe(AttendanceSource::Device)
        ->and($attendance->entry_time)->toBe('08:02:00')
        ->and($attendance->class_id)->toBe($class->id);
});
