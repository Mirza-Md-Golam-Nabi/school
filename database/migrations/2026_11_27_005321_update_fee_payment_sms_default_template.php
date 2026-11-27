<?php

use App\Enums\SmsEvent;
use App\Models\SmsSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The fee payment SMS template before {receipt_link} was added.
     */
    private const PREVIOUS_DEFAULT = 'প্রিয় অভিভাবক, {name} (ID: {student_id})-এর {amount} টাকা ফি গ্রহণ করা হয়েছে। রসিদ নং: {receipt_no} - {school}';

    /**
     * Schools that saved SMS Settings stored every visible default word for
     * word. Clear each template that is still a default (and the fee payment
     * one from before the receipt link) so they follow the default and the SMS
     * language from now on. Edited templates are left alone.
     */
    public function up(): void
    {
        SmsSetting::query()->each(function (SmsSetting $setting): void {
            $templates = $setting->templates ?? [];
            $changed = false;

            foreach (SmsEvent::cases() as $event) {
                $template = trim((string) ($templates[$event->value] ?? ''));
                $isDefault = $template === $event->defaultTemplate()
                    || ($event === SmsEvent::FeePaymentReceived && $template === self::PREVIOUS_DEFAULT);

                if ($template !== '' && $isDefault) {
                    $templates[$event->value] = '';
                    $changed = true;
                }
            }

            if ($changed) {
                $setting->forceFill(['templates' => $templates])->save();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
