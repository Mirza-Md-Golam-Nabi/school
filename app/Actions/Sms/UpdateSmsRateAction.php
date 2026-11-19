<?php

namespace App\Actions\Sms;

use App\Models\SmsSetting;

class UpdateSmsRateAction
{
    /**
     * Sets this school's per-SMS charge. Called only from the signed central
     * API — no one inside the school's admin panel can change it.
     */
    public function handle(float $ratePerSms): SmsSetting
    {
        $setting = SmsSetting::current();

        $setting->update(['rate_per_sms' => round($ratePerSms, 4)]);

        return $setting;
    }
}
