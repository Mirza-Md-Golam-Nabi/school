<?php

namespace App\Support\Sms;

use App\Enums\SmsBalanceTransactionType;
use App\Models\SmsBalanceTransaction;
use App\Models\SmsSetting;
use Illuminate\Support\Facades\DB;

/**
 * The only place the school's SMS balance changes. Every change locks the
 * settings row, so two senders can never both spend the same taka, and
 * writes a ledger line.
 */
class SmsWallet
{
    /**
     * Runs the callback with the settings row locked for update.
     *
     * @template T
     *
     * @param  callable(SmsSetting): T  $callback
     * @return T
     */
    public static function locked(callable $callback): mixed
    {
        SmsSetting::current();

        return DB::transaction(function () use ($callback) {
            $setting = SmsSetting::query()->lockForUpdate()->firstOrFail();

            return $callback($setting);
        });
    }

    /**
     * Adds (positive) or removes (negative) money and records it. Must be
     * called on a setting row obtained from locked().
     *
     * @param  array<string, mixed>  $attributes  Extra ledger columns (description, reference, batch_id…).
     */
    public static function apply(SmsSetting $setting, SmsBalanceTransactionType $type, float $amount, array $attributes = []): SmsBalanceTransaction
    {
        $newBalance = round($setting->currentBalance() + $amount, 4);

        $setting->forceFill(['balance' => $newBalance])->saveQuietly();

        return SmsBalanceTransaction::create([
            ...$attributes,
            'type' => $type,
            'amount' => round($amount, 4),
            'balance_after' => $newBalance,
        ]);
    }
}
