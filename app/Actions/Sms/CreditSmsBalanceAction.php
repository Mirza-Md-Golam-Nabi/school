<?php

namespace App\Actions\Sms;

use App\Enums\SmsBalanceTransactionType;
use App\Enums\SmsRechargeRequestStatus;
use App\Models\SmsBalanceTransaction;
use App\Models\SmsRechargeRequest;
use App\Models\SmsSetting;
use App\Support\Sms\SmsWallet;
use Illuminate\Support\Facades\Cache;

class CreditSmsBalanceAction
{
    /**
     * Adds a confirmed recharge to the school's SMS balance. Called only from
     * outside the admin panel (the signed central API, or the sms:credit
     * command). A reference that was already credited is not added again, so
     * a retried request can't double the balance.
     *
     * @return array{transaction: SmsBalanceTransaction, already_credited: bool}
     */
    public function handle(float $amount, ?string $reference = null, ?int $rechargeRequestId = null, ?string $note = null): array
    {
        if (filled($reference)) {
            $existing = SmsBalanceTransaction::query()->where('reference', $reference)->first();

            if ($existing) {
                return ['transaction' => $existing, 'already_credited' => true];
            }
        }

        $transaction = SmsWallet::locked(function (SmsSetting $setting) use ($amount, $reference, $rechargeRequestId, $note): SmsBalanceTransaction {
            $request = $rechargeRequestId ? SmsRechargeRequest::query()->lockForUpdate()->find($rechargeRequestId) : null;

            $transaction = SmsWallet::apply($setting, SmsBalanceTransactionType::Recharge, round($amount, 2), [
                'reference' => filled($reference) ? $reference : null,
                'sms_recharge_request_id' => $request?->id,
                'sms_parts' => $setting->smsCountFor($amount),
                'description' => filled($note) ? $note : 'SMS balance recharge',
            ]);

            $request?->update([
                'status' => SmsRechargeRequestStatus::Completed,
                'credited_amount' => round($amount, 2),
                'completed_at' => now(),
            ]);

            return $transaction;
        });

        // A fresh recharge should be able to raise a fresh "balance too low" alert.
        Cache::forget(QueueSmsBatchAction::SHORTFALL_ALERT_CACHE_KEY);

        return ['transaction' => $transaction, 'already_credited' => false];
    }
}
