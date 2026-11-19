<?php

namespace App\Jobs;

use App\Enums\SmsBalanceTransactionType;
use App\Enums\SmsStatus;
use App\Models\SmsLog;
use App\Models\SmsSetting;
use App\Models\SmsVendor;
use App\Support\Sms\SmsWallet;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends one already-charged SMS, trying each active vendor in priority order
 * until one accepts it. If none does, the SMS is marked failed and its cost
 * goes back to the school's balance.
 */
class SendSmsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Never retried by the queue — a retry after a vendor already accepted the
     * message would send it twice. Vendor failover happens inside handle().
     */
    public int $tries = 1;

    public function __construct(public readonly int $smsLogId) {}

    public function handle(): void
    {
        $log = SmsLog::find($this->smsLogId);

        if (! $log || $log->status !== SmsStatus::Pending) {
            return;
        }

        $errors = [];
        $lastAttempt = [];

        foreach (SmsVendor::query()->usable()->get() as $vendor) {
            try {
                $result = $vendor->gateway()->send($log->phone, $log->message);
            } catch (Throwable $exception) {
                $errors[] = "{$vendor->name}: {$exception->getMessage()}";

                continue;
            }

            if ($result->isSuccessful) {
                $log->update([
                    'status' => SmsStatus::Sent,
                    'sent_at' => now(),
                    'sms_vendor_id' => $vendor->id,
                    'vendor_message_id' => $result->messageId,
                    'response_code' => $result->responseCode,
                    'error_message' => null,
                ]);

                return;
            }

            $errors[] = "{$vendor->name}: {$result->error}";
            $lastAttempt = ['sms_vendor_id' => $vendor->id, 'response_code' => $result->responseCode];
        }

        $this->failAndRefund($errors === [] ? 'No active SMS vendor.' : implode(' | ', $errors), $lastAttempt);
    }

    public function failed(?Throwable $exception): void
    {
        $this->failAndRefund($exception?->getMessage() ?? 'SMS job failed.');
    }

    /**
     * @param  array{sms_vendor_id?: int, response_code?: ?string}  $lastAttempt  The last vendor that answered, and its code.
     */
    private function failAndRefund(string $error, array $lastAttempt = []): void
    {
        SmsWallet::locked(function (SmsSetting $setting) use ($error, $lastAttempt): void {
            $log = SmsLog::query()->lockForUpdate()->find($this->smsLogId);

            if (! $log || $log->status !== SmsStatus::Pending) {
                return;
            }

            $log->update([
                ...$lastAttempt,
                'status' => SmsStatus::Failed,
                'error_message' => mb_substr($error, 0, 1000),
            ]);

            if ((float) $log->cost > 0) {
                SmsWallet::apply($setting, SmsBalanceTransactionType::Refund, (float) $log->cost, [
                    'sms_parts' => $log->parts,
                    'batch_id' => $log->batch_id,
                    'description' => "Refund — SMS to {$log->phone} failed",
                ]);
            }
        });
    }
}
