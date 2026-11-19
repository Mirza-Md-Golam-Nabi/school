<?php

use App\Actions\Sms\SubmitSmsRechargeRequestAction;
use App\Enums\SmsBalanceTransactionType;
use App\Enums\SmsRechargeRequestStatus;
use App\Models\SmsBalanceTransaction;
use App\Models\SmsRechargeRequest;
use App\Models\SmsSetting;
use App\Models\User;
use App\Support\CentralSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'central.school_id' => 'school-42',
        'central.secret' => 'test-secret',
        'sms.recharge_request_url' => '',
    ]);

    SmsSetting::current()->update(['rate_per_sms' => 0.25]);
});

/**
 * @param  array<string, mixed>  $payload
 */
function postSmsCredit(array $payload, ?string $secret = 'test-secret'): TestResponse
{
    $body = json_encode($payload);
    $timestamp = (string) now()->timestamp;
    $signature = $secret === null ? 'bad' : hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

    return test()->call('POST', route('api.central.sms-credit'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_TIMESTAMP' => $timestamp,
        'HTTP_X_SIGNATURE' => $signature,
    ], content: $body);
}

it('adds a signed recharge from the central app and completes the request', function () {
    $request = SmsRechargeRequest::create([
        'amount' => 500, 'rate_per_sms' => 0.25, 'estimated_sms' => 2000, 'status' => SmsRechargeRequestStatus::Pending,
    ]);

    postSmsCredit(['amount' => 500, 'reference' => 'PAY-1', 'recharge_request_id' => $request->id])
        ->assertCreated()
        ->assertJson(['credited' => true, 'balance' => 500, 'sms_count' => 2000]);

    expect(SmsSetting::current()->currentBalance())->toBe(500.0)
        ->and($request->refresh()->status)->toBe(SmsRechargeRequestStatus::Completed)
        ->and((float) $request->credited_amount)->toBe(500.0)
        ->and(SmsBalanceTransaction::sole()->type)->toBe(SmsBalanceTransactionType::Recharge);
});

it('never adds the same recharge reference twice', function () {
    postSmsCredit(['amount' => 300, 'reference' => 'PAY-1'])->assertCreated();
    postSmsCredit(['amount' => 300, 'reference' => 'PAY-1'])->assertSuccessful()->assertJson(['already_credited' => true]);

    expect(SmsSetting::current()->currentBalance())->toBe(300.0)
        ->and(SmsBalanceTransaction::count())->toBe(1);
});

it('rejects a recharge that is not signed with this school secret', function () {
    postSmsCredit(['amount' => 300, 'reference' => 'PAY-1'], secret: 'wrong-secret')->assertForbidden();
    postSmsCredit(['amount' => 300, 'reference' => 'PAY-2'], secret: null)->assertForbidden();

    expect(SmsSetting::current()->currentBalance())->toBe(0.0);
});

it('rejects a recharge whose body was changed after signing', function () {
    $timestamp = (string) now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.json_encode(['amount' => 10, 'reference' => 'PAY-1']), 'test-secret');

    test()->call('POST', route('api.central.sms-credit'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_TIMESTAMP' => $timestamp,
        'HTTP_X_SIGNATURE' => $signature,
    ], content: json_encode(['amount' => 10000, 'reference' => 'PAY-1']))->assertForbidden();

    expect(SmsSetting::current()->currentBalance())->toBe(0.0);
});

it('adds balance from the sms:credit command', function () {
    $this->artisan('sms:credit', ['amount' => 250, '--reference' => 'MANUAL-1'])->assertSuccessful();
    $this->artisan('sms:credit', ['amount' => 250, '--reference' => 'MANUAL-1'])->assertSuccessful();

    expect(SmsSetting::current()->currentBalance())->toBe(250.0);
});

it('saves a recharge request with its SMS count even before the request link exists', function () {
    Http::preventStrayRequests();

    $request = app(SubmitSmsRechargeRequestAction::class)->handle(100, 'bKash TrxID 8ABC', User::factory()->create());

    expect($request->estimated_sms)->toBe(400)
        ->and($request->status)->toBe(SmsRechargeRequestStatus::Pending)
        ->and($request->delivered_at)->toBeNull()
        ->and($request->delivery_error)->toContain('not configured');
});

it('posts a signed recharge request to the request link', function () {
    config(['sms.recharge_request_url' => 'https://central.test/api/sms-recharge-requests']);
    Http::fake(['central.test/*' => Http::response(['ok' => true])]);

    $request = app(SubmitSmsRechargeRequestAction::class)->handle(100, null, User::factory()->create());

    expect($request->refresh()->delivered_at)->not->toBeNull();

    Http::assertSent(fn (Request $sent): bool => $sent['school_id'] === 'school-42'
        && $sent['amount'] == 100
        && $sent['estimated_sms'] === 400
        && $sent->header('X-Signature')[0] === CentralSignature::sign($sent->header('X-Timestamp')[0], $sent->body()));
});
