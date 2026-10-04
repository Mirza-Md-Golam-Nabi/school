<?php

use App\Enums\DeviceUserRemovalStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\AttendanceDevice;
use App\Models\Classes;
use App\Models\DeviceUser;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['attendance.auto_enroll' => false]);
});

function syncApiDevice(): AttendanceDevice
{
    return AttendanceDevice::factory()->withToken('sync-token')->create();
}

function syncApiEnrolled(AttendanceDevice $device, string $enrollId, string $name = 'Rahim Uddin', array $state = []): DeviceUser
{
    $class = Classes::firstOrCreate(['name' => 'Class 5'], ['order' => 5, 'is_active' => true]);

    $student = StudentProfile::create([
        'user_id' => User::factory()->create(['name' => $name, 'user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => (int) $enrollId,
        'current_class_id' => $class->id,
        'session_year' => 2026,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    return DeviceUser::factory()->forPerson($student)->create(
        ['attendance_device_id' => $device->id, 'enroll_id' => $enrollId] + $state
    );
}

function syncApiPlan(): TestResponse
{
    return test()->withToken('sync-token')->getJson(route('api.device.sync-plan'));
}

function syncApiReport(array $payload): TestResponse
{
    return test()->withToken('sync-token')->postJson(route('api.device.sync-report'), $payload);
}

it('requires a valid active device token for both sync endpoints', function () {
    AttendanceDevice::factory()->withToken('inactive-token')->inactive()->create();

    $this->getJson(route('api.device.sync-plan'))->assertUnauthorized();
    $this->postJson(route('api.device.sync-report'), ['users' => []])->assertUnauthorized();
    $this->withToken('inactive-token')->getJson(route('api.device.sync-plan'))->assertUnauthorized();
    $this->withToken('nope')->postJson(route('api.device.sync-report'), ['users' => []])->assertUnauthorized();
});

it('lists everyone on the roster with device-friendly names', function () {
    $device = syncApiDevice();
    $plain = syncApiEnrolled($device, '1', 'Rahim Uddin', ['card_number' => '778899']);
    $bengali = syncApiEnrolled($device, '2', 'রহিম উদ্দিন আহমেদ');
    $long = syncApiEnrolled($device, '3', 'Mohammad Abdullah Al Mamun Chowdhury');
    $removed = syncApiEnrolled($device, '4');
    $removed->markRemoved();

    $users = collect(syncApiPlan()->assertSuccessful()->json('users'))->keyBy('enroll_id');

    expect($users->pluck('enroll_id')->all())->toBe(['1', '2', '3'])
        ->and($users['1'])->toBe(['enroll_id' => '1', 'name' => 'Rahim Uddin', 'card_number' => '778899'])
        ->and($users['2']['name'])->toMatch('/^[A-Za-z0-9 .\-]+$/')
        ->and(mb_strlen($users['3']['name']))->toBeLessThanOrEqual(24)
        ->and($users['3']['name'])->toStartWith('Mohammad Abdullah');
});

it('falls back to the enroll id when a person has no usable name', function () {
    $device = syncApiDevice();
    syncApiEnrolled($device, '7', '???');

    expect(syncApiPlan()->json('users.0.name'))->toBe('ID 7');
});

it('keeps people in the users list until their removal is actually due', function () {
    $this->travelTo('2026-12-01 10:00:00');
    $device = syncApiDevice();
    syncApiEnrolled($device, '1', state: ['last_seen_on_device_at' => now(), 'removal_status' => DeviceUserRemovalStatus::Queued, 'removal_due_at' => now()->addDay()]);
    syncApiEnrolled($device, '2', state: ['last_seen_on_device_at' => now(), 'removal_status' => DeviceUserRemovalStatus::PendingApproval]);
    syncApiEnrolled($device, '3', state: ['last_seen_on_device_at' => now(), 'removal_status' => DeviceUserRemovalStatus::Queued, 'removal_due_at' => now()->subMinute()]);

    $plan = syncApiPlan()->assertSuccessful()->json();

    expect(collect($plan['users'])->pluck('enroll_id')->all())->toBe(['1', '2'])
        ->and($plan['remove'])->toBe(['3']);
});

it('never asks for more removals per sync than the configured limit', function () {
    config(['attendance.max_removals_per_sync' => 2]);
    $device = syncApiDevice();

    foreach (['1', '2', '3'] as $enrollId) {
        syncApiEnrolled($device, $enrollId, state: ['last_seen_on_device_at' => now(), 'removal_status' => DeviceUserRemovalStatus::Queued, 'removal_due_at' => now()->subHour()]);
    }

    $plan = syncApiPlan()->json();

    expect($plan['remove'])->toHaveCount(2)
        ->and($plan['users'])->toHaveCount(1);
});

it('records card numbers, fingerprints and when each person was last seen on the device', function () {
    $this->travelTo('2026-12-01 10:00:00');
    $device = syncApiDevice();
    $withCard = syncApiEnrolled($device, '1');
    $noCard = syncApiEnrolled($device, '2', state: ['card_number' => 'OLD']);
    $missing = syncApiEnrolled($device, '3');

    syncApiReport([
        'users' => [
            ['enroll_id' => 1, 'card_number' => 4455667788, 'fingerprint_count' => 2],
            ['enroll_id' => '2', 'card_number' => 0, 'fingerprint_count' => 0],
        ],
    ])->assertSuccessful()->assertJson(['updated' => 2, 'removed' => 0, 'unknown' => 0]);

    expect($withCard->fresh())
        ->card_number->toBe('4455667788')
        ->fingerprint_count->toBe(2)
        ->last_seen_on_device_at->toDateTimeString()->toBe('2026-12-01 10:00:00')
        ->and($noCard->fresh()->card_number)->toBeNull()
        ->and($missing->fresh()->last_seen_on_device_at)->toBeNull();
});

it('lists users on the device that the software does not know without touching them', function () {
    $device = syncApiDevice();
    $known = syncApiEnrolled($device, '1');
    $wasRemoved = syncApiEnrolled($device, '2');
    $wasRemoved->markRemoved();

    syncApiReport([
        'users' => [
            ['enroll_id' => '1'],
            ['enroll_id' => '2', 'name' => 'Came Back'],
            ['enroll_id' => '900', 'name' => 'Test User', 'card_number' => '123'],
        ],
    ])->assertSuccessful()->assertJson(['updated' => 1, 'unknown' => 2]);

    expect($device->fresh()->unknown_device_users)->toBe([
        ['enroll_id' => '2', 'name' => 'Came Back', 'card_number' => null],
        ['enroll_id' => '900', 'name' => 'Test User', 'card_number' => '123'],
    ])->and($known->fresh()->isRemoved())->toBeFalse();
});

it('marks deletions the client carried out as removed, but only scheduled ones', function () {
    $device = syncApiDevice();
    $scheduled = syncApiEnrolled($device, '1', state: ['last_seen_on_device_at' => now(), 'removal_status' => DeviceUserRemovalStatus::Queued, 'removal_due_at' => now()->subHour(), 'card_number' => '555']);
    $pending = syncApiEnrolled($device, '2', state: ['last_seen_on_device_at' => now(), 'removal_status' => DeviceUserRemovalStatus::PendingApproval]);
    $normal = syncApiEnrolled($device, '3', state: ['last_seen_on_device_at' => now()]);

    syncApiReport(['users' => [], 'removed' => ['1', '2', '3', '999']])
        ->assertSuccessful()
        ->assertJson(['removed' => 1]);

    expect($scheduled->fresh()->isRemoved())->toBeTrue()
        ->and($scheduled->fresh()->card_number)->toBeNull()
        ->and($pending->fresh()->isRemoved())->toBeFalse()
        ->and($normal->fresh()->isRemoved())->toBeFalse();
});

it('counts what the device holds from the reported user list and measures it against the admin-entered capacity', function () {
    $this->travelTo('2026-12-01 10:00:00');
    $device = syncApiDevice();
    $device->update(['user_capacity' => 1000, 'fingerprint_capacity' => 3000, 'card_capacity' => 3000, 'record_capacity' => 100000]);
    syncApiEnrolled($device, '1');
    syncApiEnrolled($device, '2');

    syncApiReport([
        'users' => [
            ['enroll_id' => '1', 'card_number' => '111', 'fingerprint_count' => 2],
            ['enroll_id' => '2', 'card_number' => '0', 'fingerprint_count' => 1],
            ['enroll_id' => '900', 'card_number' => '222', 'fingerprint_count' => 0],
        ],
        'sizes' => ['records' => 1200, 'users_cap' => 9999, 'fingers_cap' => 9999],
    ])->assertSuccessful();

    $device->refresh();

    expect($device->usageOf('users'))->toBe(['used' => 3, 'limit' => 1000, 'percent' => 0])
        ->and($device->usageOf('fingers'))->toBe(['used' => 3, 'limit' => 3000, 'percent' => 0])
        ->and($device->usageOf('cards'))->toBe(['used' => 2, 'limit' => 3000, 'percent' => 0])
        ->and($device->usageOf('records'))->toBe(['used' => 1200, 'limit' => 100000, 'percent' => 1])
        ->and($device->reported_sizes)->not->toHaveKeys(['users_cap', 'fingers_cap'])
        ->and($device->sizes_reported_at->toDateTimeString())->toBe('2026-12-01 10:00:00')
        ->and($device->capacitySummary())->toContain('Users 3/1000 (0%)', 'Records 1200/100000 (1%)');
});

it('never takes capacity limits from the device report', function () {
    $device = syncApiDevice();
    $device->update(['user_capacity' => 1000]);

    syncApiReport(['users' => [], 'sizes' => ['users_cap' => 3000]])->assertSuccessful();

    expect($device->fresh()->user_capacity)->toBe(1000);
});

it('shows only the used count until a capacity is entered', function () {
    $device = syncApiDevice();

    syncApiReport(['users' => [['enroll_id' => '1']], 'sizes' => ['records' => 15]])->assertSuccessful();

    expect($device->fresh()->usageOf('users'))->toBe(['used' => 1, 'limit' => null, 'percent' => null])
        ->and($device->fresh()->capacitySummary())->toContain('Users 1 ·');
});

it('keeps the previous record total when a report leaves it out', function () {
    $device = syncApiDevice();

    syncApiReport(['users' => [], 'sizes' => ['records' => 500]])->assertSuccessful();
    syncApiReport(['users' => []])->assertSuccessful();

    expect($device->fresh()->reported_sizes['records'])->toBe(500);
});

it('tells the sync client after how many days to clear the device log', function () {
    $device = syncApiDevice();

    expect(syncApiPlan()->assertSuccessful()->json())->toHaveKey('log_retention_days', null);

    $device->update(['log_retention_days' => 30]);

    expect(syncApiPlan()->json('log_retention_days'))->toBe(30);
});

it('records when the sync client cleared the device log', function () {
    $this->travelTo('2026-12-01 10:00:00');
    $device = syncApiDevice();

    syncApiReport(['users' => [], 'sizes' => ['records' => 0], 'log_cleared' => true])->assertSuccessful();

    expect($device->fresh()->log_cleared_at->toDateTimeString())->toBe('2026-12-01 10:00:00');

    $this->travelTo('2026-12-02 10:00:00');
    syncApiReport(['users' => []])->assertSuccessful();
    syncApiReport(['users' => [], 'log_cleared' => false])->assertSuccessful();

    expect($device->fresh()->log_cleared_at->toDateTimeString())->toBe('2026-12-01 10:00:00');
});

it('validates the sync report', function (array $payload, string $invalidKey) {
    syncApiDevice();

    syncApiReport($payload)->assertUnprocessable()->assertJsonValidationErrors($invalidKey);
})->with([
    'users missing' => [[], 'users'],
    'enroll id missing' => [['users' => [['card_number' => '1']]], 'users.0.enroll_id'],
    'enroll id too long' => [['users' => [['enroll_id' => str_repeat('9', 51)]]], 'users.0.enroll_id'],
    'too many fingerprints' => [['users' => [['enroll_id' => '1', 'fingerprint_count' => 11]]], 'users.0.fingerprint_count'],
    'negative record count' => [['users' => [], 'sizes' => ['records' => -1]], 'sizes.records'],
    'log cleared not a boolean' => [['users' => [], 'log_cleared' => 'yes'], 'log_cleared'],
]);
