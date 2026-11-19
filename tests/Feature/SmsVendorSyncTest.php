<?php

use App\Enums\SmsSource;
use App\Enums\SmsStatus;
use App\Enums\SmsVendorDriver;
use App\Models\SmsLog;
use App\Models\SmsVendor;
use App\Support\CentralSecretBox;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['central.school_id' => 'school-42', 'central.secret' => 'test-secret']);
});

/**
 * Send the vendors exactly the way the central app does: each API key sealed
 * with this school's secret, and the raw JSON body signed with the timestamp.
 *
 * @param  array<string, mixed>  $payload
 */
function postSmsVendors(array $payload, string $secret = 'test-secret'): TestResponse
{
    $body = json_encode($payload);
    $timestamp = (string) now()->timestamp;

    return test()->call('POST', route('api.central.sms-vendors'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_TIMESTAMP' => $timestamp,
        'HTTP_X_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$body, $secret),
    ], content: $body);
}

function sentSmsLogFor(SmsVendor $vendor): SmsLog
{
    return SmsLog::create([
        'source_type' => SmsSource::Other,
        'phone' => '8801711000000',
        'message' => 'Test',
        'status' => SmsStatus::Sent,
        'sms_vendor_id' => $vendor->id,
    ]);
}

/**
 * @return array<string, mixed>
 */
function centralVendor(int $id, array $overrides = []): array
{
    return [
        'id' => $id,
        'name' => 'BulkSMSBD',
        'driver' => 'bulksmsbd',
        'api_key' => CentralSecretBox::seal('plain-key-'.$id),
        'sender_id' => '8809617613451',
        'priority' => 1,
        'is_active' => true,
        ...$overrides,
    ];
}

it('makes the school vendors an exact copy of the central list, with the API key opened and stored encrypted', function () {
    $local = SmsVendor::factory()->create(['name' => 'Old local vendor']);
    $log = sentSmsLogFor($local);

    postSmsVendors(['vendors' => [
        centralVendor(7),
        centralVendor(9, ['name' => 'Backup', 'priority' => 2, 'is_active' => false]),
    ]])->assertOk()->assertJson(['synced' => 2]);

    $vendor = SmsVendor::where('central_id', 7)->sole();

    expect(SmsVendor::count())->toBe(2)
        ->and($vendor->api_key)->toBe('plain-key-7')
        ->and($vendor->getRawOriginal('api_key'))->not->toContain('plain-key-7')
        ->and($vendor->driver)->toBe(SmsVendorDriver::BulkSmsBd)
        ->and($vendor->is_active)->toBeTrue()
        ->and(SmsVendor::where('central_id', 9)->sole()->is_active)->toBeFalse()
        ->and($log->refresh()->sms_vendor_id)->toBeNull();
});

it('updates a vendor in place on the next sync, keeping its SMS logs', function () {
    postSmsVendors(['vendors' => [centralVendor(7)]])->assertOk();
    $vendor = SmsVendor::sole();
    $log = sentSmsLogFor($vendor);

    postSmsVendors(['vendors' => [centralVendor(7, ['api_key' => CentralSecretBox::seal('rotated-key'), 'sender_id' => '8809600000000'])]])->assertOk();

    expect(SmsVendor::sole())
        ->id->toBe($vendor->id)
        ->api_key->toBe('rotated-key')
        ->sender_id->toBe('8809600000000')
        ->and($log->refresh()->sms_vendor_id)->toBe($vendor->id);
});

it('rejects vendors that are not signed with this school secret', function () {
    $vendor = SmsVendor::factory()->create();

    postSmsVendors(['vendors' => [centralVendor(7)]], secret: 'wrong-secret')->assertForbidden();

    expect(SmsVendor::sole()->id)->toBe($vendor->id);
});

it('rejects an API key sealed for another school', function () {
    $otherSchoolBox = new Encrypter(hash_hmac('sha256', 'sms-vendor-api-key', 'other-secret', true), 'aes-256-gcm');

    postSmsVendors(['vendors' => [centralVendor(7, ['api_key' => $otherSchoolBox->encryptString('stolen')])]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('vendors.0.api_key');

    expect(SmsVendor::count())->toBe(0);
});

it('rejects an unusable vendor list', function (array $payload, string $error) {
    postSmsVendors($payload)->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'no list' => [[], 'vendors'],
    'unknown provider' => [fn () => ['vendors' => [centralVendor(1, ['driver' => 'unknown'])]], 'vendors.0.driver'],
    'plain API key' => [fn () => ['vendors' => [centralVendor(1, ['api_key' => 'plain'])]], 'vendors.0.api_key'],
    'same vendor twice' => [fn () => ['vendors' => [centralVendor(1), centralVendor(1)]], 'vendors.0.id'],
]);
