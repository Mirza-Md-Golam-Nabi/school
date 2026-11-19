<?php

use App\Models\SmsSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['central.school_id' => 'school-42', 'central.secret' => 'test-secret']);
});

/**
 * Send the rate exactly the way the central app does: the raw JSON body
 * signed together with the timestamp.
 *
 * @param  array<string, mixed>  $payload
 */
function postSmsRate(array $payload, string $secret = 'test-secret', ?string $signedBody = null): TestResponse
{
    $body = json_encode($payload);
    $timestamp = (string) now()->timestamp;

    return test()->call('POST', route('api.central.sms-rate'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_TIMESTAMP' => $timestamp,
        'HTTP_X_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.($signedBody ?? $body), $secret),
    ], content: $body);
}

it('sets the per-SMS charge sent by the central app', function () {
    $setting = SmsSetting::current();
    $setting->forceFill(['balance' => 100])->save();

    postSmsRate(['rate_per_sms' => 0.35])
        ->assertOk()
        ->assertJson(['rate_per_sms' => 0.35, 'balance' => 100, 'sms_count' => 285]);

    expect(SmsSetting::current()->ratePerSms())->toBe(0.35);
});

it('rejects a rate that is not signed with this school secret', function () {
    SmsSetting::current()->update(['rate_per_sms' => 0.25]);

    postSmsRate(['rate_per_sms' => 0.01], secret: 'wrong-secret')->assertForbidden();
    postSmsRate(['rate_per_sms' => 0.01], signedBody: json_encode(['rate_per_sms' => 0.25]))->assertForbidden();

    expect(SmsSetting::current()->ratePerSms())->toBe(0.25);
});

it('rejects an unusable rate', function (mixed $rate) {
    postSmsRate(['rate_per_sms' => $rate])->assertUnprocessable()->assertJsonValidationErrors('rate_per_sms');
})->with([null, -1, 101, 'abc']);
