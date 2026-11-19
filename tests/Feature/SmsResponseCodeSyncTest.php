<?php

use App\Enums\SmsVendorDriver;
use App\Models\SmsVendorResponseCode;
use Database\Seeders\SmsVendorResponseCodeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['central.school_id' => 'school-42', 'central.secret' => 'test-secret']);
});

/**
 * Send the codes exactly the way the central app does: the raw JSON body
 * signed together with the timestamp.
 *
 * @param  array<string, mixed>  $payload
 */
function postResponseCodes(array $payload, string $secret = 'test-secret', ?string $signedBody = null): TestResponse
{
    $body = json_encode($payload);
    $timestamp = (string) now()->timestamp;

    return test()->call('POST', route('api.central.sms-response-codes'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_TIMESTAMP' => $timestamp,
        'HTTP_X_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.($signedBody ?? $body), $secret),
    ], content: $body);
}

it('makes the school codes an exact copy of the central list', function () {
    $this->seed(SmsVendorResponseCodeSeeder::class);

    postResponseCodes(['codes' => [
        ['driver' => 'bulksmsbd', 'code' => '202', 'message' => 'Submitted', 'is_success' => true],
        ['driver' => 'bulksmsbd', 'code' => '1007', 'message' => 'Low balance', 'is_success' => false],
        ['driver' => 'bulksmsbd', 'code' => '1040', 'message' => 'New provider error', 'is_success' => false],
    ]])->assertOk()->assertJson(['synced' => 3]);

    expect(SmsVendorResponseCode::pluck('code')->all())->toEqualCanonicalizing(['202', '1007', '1040'])
        ->and(SmsVendorResponseCode::lookup(SmsVendorDriver::BulkSmsBd, 1007)->message)->toBe('Low balance')
        ->and(SmsVendorResponseCode::lookup(SmsVendorDriver::BulkSmsBd, 202)->is_success)->toBeTrue()
        ->and(SmsVendorResponseCode::lookup(SmsVendorDriver::BulkSmsBd, 1032))->toBeNull();
});

it('rejects codes that are not signed with this school secret', function () {
    $this->seed(SmsVendorResponseCodeSeeder::class);
    $payload = ['codes' => []];

    postResponseCodes($payload, secret: 'wrong-secret')->assertForbidden();
    postResponseCodes($payload, signedBody: json_encode(['codes' => [['driver' => 'bulksmsbd', 'code' => '1', 'message' => 'x', 'is_success' => true]]]))->assertForbidden();

    expect(SmsVendorResponseCode::count())->toBe(20);
});

it('rejects an unusable list without touching the existing codes', function (array $payload, string $error) {
    $this->seed(SmsVendorResponseCodeSeeder::class);

    postResponseCodes($payload)->assertUnprocessable()->assertJsonValidationErrors($error);

    expect(SmsVendorResponseCode::count())->toBe(20);
})->with([
    'no list' => [[], 'codes'],
    'unknown provider' => [['codes' => [['driver' => 'unknown', 'code' => '1', 'message' => 'x', 'is_success' => false]]], 'codes.0.driver'],
    'missing meaning' => [['codes' => [['driver' => 'bulksmsbd', 'code' => '1', 'is_success' => false]]], 'codes.0.message'],
    'same code twice' => [['codes' => [
        ['driver' => 'bulksmsbd', 'code' => '1', 'message' => 'a', 'is_success' => false],
        ['driver' => 'bulksmsbd', 'code' => '1', 'message' => 'b', 'is_success' => false],
    ]], 'codes'],
]);
