<?php

use App\Actions\Sms\QueueSmsBatchAction;
use App\Enums\SmsBalanceTransactionType;
use App\Enums\SmsEvent;
use App\Enums\SmsStatus;
use App\Enums\SmsVendorDriver;
use App\Enums\UserType;
use App\Jobs\SendSmsJob;
use App\Models\SmsBalanceTransaction;
use App\Models\SmsLog;
use App\Models\SmsSetting;
use App\Models\SmsVendor;
use App\Models\SmsVendorResponseCode;
use App\Models\User;
use App\Support\Sms\OutgoingSms;
use App\Support\Sms\SmsBatchResult;
use Database\Seeders\SmsVendorResponseCodeSeeder;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

function configureSmsForSending(float $balance, float $rate = 0.25): SmsSetting
{
    $setting = SmsSetting::current();
    $setting->fill(['is_enabled' => true, 'rate_per_sms' => $rate, 'enabled_events' => array_map(fn (SmsEvent $event) => $event->value, SmsEvent::cases())])->save();
    $setting->forceFill(['balance' => $balance])->save();

    return $setting->refresh();
}

/**
 * @return list<OutgoingSms>
 */
function outgoingSmsTo(array $phones, string $message = 'Hello guardian'): array
{
    return array_map(fn (string $phone) => new OutgoingSms($phone, $message, SmsEvent::AttendanceAbsent, 1, 'Rahim'), $phones);
}

it('charges and queues every SMS of a batch the balance can cover', function () {
    Queue::fake();
    configureSmsForSending(balance: 10);
    SmsVendor::factory()->create();

    $result = app(QueueSmsBatchAction::class)->handle(outgoingSmsTo(['01711111111', '01822222222']));

    expect($result->status)->toBe(SmsBatchResult::QUEUED)
        ->and($result->queued)->toBe(2)
        ->and($result->cost)->toBe(0.5)
        ->and(SmsSetting::current()->currentBalance())->toBe(9.5)
        ->and(SmsLog::where('status', SmsStatus::Pending)->count())->toBe(2)
        ->and(SmsLog::first()->phone)->toBe('8801711111111');

    expect(SmsBalanceTransaction::sole())
        ->type->toBe(SmsBalanceTransactionType::Debit)
        ->and((float) SmsBalanceTransaction::sole()->amount)->toBe(-0.5)
        ->and(SmsBalanceTransaction::sole()->sms_parts)->toBe(2);

    Queue::assertPushed(SendSmsJob::class, 2);
});

it('sends nothing at all when the balance cannot cover the whole batch', function () {
    Queue::fake();
    Notification::fake();
    configureSmsForSending(balance: 0.5);
    SmsVendor::factory()->create();

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $admin->assignRole('admin');

    $result = app(QueueSmsBatchAction::class)->handle(outgoingSmsTo(['01711111111', '01822222222', '01933333333']));

    expect($result->status)->toBe(SmsBatchResult::INSUFFICIENT_BALANCE)
        ->and($result->cost)->toBe(0.75)
        ->and(SmsSetting::current()->currentBalance())->toBe(0.5)
        ->and(SmsLog::count())->toBe(0)
        ->and(SmsBalanceTransaction::count())->toBe(0);

    Queue::assertNotPushed(SendSmsJob::class);
    Notification::assertSentTo($admin, DatabaseNotification::class);

    // A second shortfall right after must not alert the admins again.
    app(QueueSmsBatchAction::class)->handle(outgoingSmsTo(['01711111111', '01822222222', '01933333333']));

    Notification::assertSentToTimes($admin, DatabaseNotification::class, 1);
});

it('charges per SMS part, so a long Bangla message costs more', function () {
    Queue::fake();
    configureSmsForSending(balance: 10, rate: 0.5);
    SmsVendor::factory()->create();

    $result = app(QueueSmsBatchAction::class)->handle(outgoingSmsTo(['01711111111'], str_repeat('ক', 71)));

    expect($result->parts)->toBe(2)
        ->and($result->cost)->toBe(1.0)
        ->and(SmsLog::sole()->parts)->toBe(2);
});

it('skips invalid numbers and sends one SMS per number and text', function () {
    Queue::fake();
    configureSmsForSending(balance: 10);
    SmsVendor::factory()->create();

    $result = app(QueueSmsBatchAction::class)->handle(outgoingSmsTo(['01711111111', '+8801711111111', '12345', '']));

    expect($result->queued)->toBe(1)
        ->and($result->skippedInvalidPhone)->toBe(2)
        ->and(SmsLog::count())->toBe(1);
});

it('does not send when SMS is switched off or no vendor is active', function () {
    Queue::fake();
    configureSmsForSending(balance: 10);
    SmsVendor::factory()->inactive()->create();

    expect(app(QueueSmsBatchAction::class)->handle(outgoingSmsTo(['01711111111']))->status)->toBe(SmsBatchResult::NO_VENDOR);

    SmsSetting::current()->update(['is_enabled' => false]);

    expect(app(QueueSmsBatchAction::class)->handle(outgoingSmsTo(['01711111111']))->status)->toBe(SmsBatchResult::DISABLED)
        ->and(SmsSetting::current()->currentBalance())->toBe(10.0);
});

it('refuses a bulk batch the provider account cannot pay for, without revealing its balance', function () {
    Queue::fake();
    Http::fake(['*getBalanceApi*' => Http::response(['response_code' => 202, 'balance' => 0.3])]);
    configureSmsForSending(balance: 100);
    SmsVendor::factory()->create();

    $result = app(QueueSmsBatchAction::class)->handle(outgoingSmsTo(['01711111111', '01822222222']));

    expect($result->status)->toBe(SmsBatchResult::PROVIDER_UNAVAILABLE)
        ->and($result->rejectionMessage())->not->toContain('0.3')
        ->and(SmsSetting::current()->currentBalance())->toBe(100.0);

    Queue::assertNothingPushed();
});

it('delivers through the vendor API with the vendor sender ID', function () {
    Http::fake([
        '*getBalanceApi*' => Http::response(['response_code' => 202, 'balance' => 500]),
        '*smsapi*' => Http::response(['response_code' => 202, 'message_id' => 'abc-1', 'success_message' => 'SMS Submitted Successfully']),
    ]);
    configureSmsForSending(balance: 10);
    $vendor = SmsVendor::factory()->create(['api_key' => 'secret-key', 'sender_id' => '8809617613451']);

    app(QueueSmsBatchAction::class)->handle(outgoingSmsTo(['01711111111'], 'Test SMS'));

    expect(SmsLog::sole())
        ->status->toBe(SmsStatus::Sent)
        ->sms_vendor_id->toBe($vendor->id)
        ->vendor_message_id->toBe('abc-1');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/smsapi')
        && $request['api_key'] === 'secret-key'
        && $request['senderid'] === '8809617613451'
        && $request['number'] === '8801711111111'
        && $request['message'] === 'Test SMS');
});

it('falls back to the next active vendor when the first one fails', function () {
    configureSmsForSending(balance: 10);
    $primary = SmsVendor::factory()->create(['name' => 'Primary', 'priority' => 1, 'api_key' => 'primary-key']);
    $backup = SmsVendor::factory()->create(['name' => 'Backup', 'priority' => 2, 'api_key' => 'backup-key']);

    Http::fake(function (Request $request) {
        return $request['api_key'] === 'primary-key'
            ? Http::response(['response_code' => 1007, 'error_message' => 'Balance Insufficient'])
            : Http::response(['response_code' => 202, 'message_id' => 'b-1']);
    });

    app(QueueSmsBatchAction::class)->handle(outgoingSmsTo(['01711111111']));

    expect(SmsLog::sole())->status->toBe(SmsStatus::Sent)->sms_vendor_id->toBe($backup->id)
        ->and($primary->id)->not->toBe($backup->id);
});

it('marks the SMS failed and refunds its cost when every vendor fails', function () {
    Http::fake(['*' => Http::response(['response_code' => 1002, 'error_message' => 'Sender Id not correct'])]);
    configureSmsForSending(balance: 10);
    SmsVendor::factory()->create();

    app(QueueSmsBatchAction::class)->handle(outgoingSmsTo(['01711111111']));

    expect(SmsLog::sole())->status->toBe(SmsStatus::Failed)
        ->and(SmsLog::sole()->error_message)->toContain('1002')
        ->and(SmsSetting::current()->currentBalance())->toBe(10.0)
        ->and(SmsBalanceTransaction::where('type', SmsBalanceTransactionType::Refund)->count())->toBe(1);
});

it('explains a failure with the meaning stored for that provider code', function () {
    $this->seed(SmsVendorResponseCodeSeeder::class);
    Http::fake(['*' => Http::response(['response_code' => 1032])]);
    configureSmsForSending(balance: 10);
    $vendor = SmsVendor::factory()->create(['name' => 'BulkSMSBD']);

    app(QueueSmsBatchAction::class)->handle(outgoingSmsTo(['01711111111']));

    expect(SmsLog::sole())
        ->status->toBe(SmsStatus::Failed)
        ->response_code->toBe('1032')
        ->sms_vendor_id->toBe($vendor->id)
        ->error_message->toBe('BulkSMSBD: [1032] ip Not whitelisted');
});

it('stores the success code of a delivered SMS', function () {
    $this->seed(SmsVendorResponseCodeSeeder::class);
    Http::fake(['*' => Http::response(['response_code' => 202, 'message_id' => 'm-1'])]);
    configureSmsForSending(balance: 10);
    SmsVendor::factory()->create();

    app(QueueSmsBatchAction::class)->handle(outgoingSmsTo(['01711111111']));

    expect(SmsLog::sole())->status->toBe(SmsStatus::Sent)->response_code->toBe('202');
});

it('lets the response code table decide which codes mean success', function () {
    SmsVendorResponseCode::create(['driver' => SmsVendorDriver::BulkSmsBd, 'code' => '200', 'message' => 'Queued', 'is_success' => true]);
    SmsVendorResponseCode::create(['driver' => SmsVendorDriver::BulkSmsBd, 'code' => '202', 'message' => 'Rejected', 'is_success' => false]);
    $vendor = SmsVendor::factory()->create();

    Http::fake(['*' => Http::sequence()->push(['response_code' => 200])->push(['response_code' => 202])]);

    expect($vendor->gateway()->send('8801711111111', 'Hi')->isSuccessful)->toBeTrue()
        ->and($vendor->gateway()->send('8801711111111', 'Hi')->isSuccessful)->toBeFalse();
});

it('seeds every BulkSMSBD response code with only 202 as success', function () {
    $this->seed(SmsVendorResponseCodeSeeder::class);
    $this->seed(SmsVendorResponseCodeSeeder::class);

    $codes = SmsVendorResponseCode::where('driver', SmsVendorDriver::BulkSmsBd)->get();

    expect($codes)->toHaveCount(20)
        ->and($codes->where('is_success', true)->pluck('code')->all())->toBe(['202'])
        ->and(SmsVendorResponseCode::lookup(SmsVendorDriver::BulkSmsBd, 1007)->message)->toBe('Balance Insufficient');
});

it('never sends or refunds the same SMS twice', function () {
    Http::fake(['*' => Http::response(['response_code' => 202])]);
    configureSmsForSending(balance: 10);
    SmsVendor::factory()->create();

    app(QueueSmsBatchAction::class)->handle(outgoingSmsTo(['01711111111']));
    (new SendSmsJob(SmsLog::sole()->id))->handle();

    Http::assertSentCount(1);
});
