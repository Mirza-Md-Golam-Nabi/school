<?php

namespace App\Console\Commands;

use App\Actions\Sms\CreditSmsBalanceAction;
use App\Models\SmsSetting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sms:credit {amount : Taka to add to the school\'s SMS balance} {--reference= : Unique payment reference, so the same recharge is never added twice} {--request= : The recharge request id this payment completes} {--note= : Shown in the balance history}')]
#[Description('Add a confirmed recharge to this school\'s SMS balance.')]
class CreditSmsBalance extends Command
{
    public function handle(CreditSmsBalanceAction $action): int
    {
        $amount = $this->argument('amount');

        if (! is_numeric($amount) || (float) $amount <= 0) {
            $this->error('Amount must be a positive number.');

            return self::FAILURE;
        }

        $result = $action->handle(
            (float) $amount,
            $this->option('reference') ?: null,
            $this->option('request') ? (int) $this->option('request') : null,
            $this->option('note') ?: null,
        );

        if ($result['already_credited']) {
            $this->warn('This reference was already credited — nothing added.');

            return self::SUCCESS;
        }

        $setting = SmsSetting::current();

        $this->info(sprintf('Added ৳%s. Balance: ৳%s (≈ %d SMS).', number_format((float) $amount, 2), number_format($setting->currentBalance(), 2), $setting->smsCountFor()));

        return self::SUCCESS;
    }
}
