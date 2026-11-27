<?php

namespace App\Models;

use App\Enums\SmsEvent;
use App\Enums\SmsLanguage;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * This school's SMS configuration — a single row. `balance` is the school's
 * own prepaid SMS balance (taka), never the provider account's real balance;
 * it only changes through SmsWallet so every change lands in the ledger.
 */
class SmsSetting extends Model
{
    use LogsActivity;

    protected $fillable = [
        'is_enabled',
        'rate_per_sms',
        'enabled_events',
        'templates',
        'language',
    ];

    protected $casts = [
        'language' => SmsLanguage::class,
        'is_enabled' => 'boolean',
        'rate_per_sms' => 'decimal:4',
        'balance' => 'decimal:4',
        'enabled_events' => 'array',
        'templates' => 'array',
    ];

    public static function current(): self
    {
        return self::firstOrCreate([], [
            'is_enabled' => false,
            'rate_per_sms' => 0,
            'enabled_events' => [],
            'templates' => [],
        ]);
    }

    /**
     * Whether SMS should go out for this event: the master switch is on, a
     * rate is set, and the admin ticked the event.
     */
    public function isEventEnabled(SmsEvent $event): bool
    {
        return $this->is_enabled
            && $this->ratePerSms() > 0
            && in_array($event->value, $this->enabled_events ?? [], true);
    }

    public function template(SmsEvent $event): string
    {
        $template = trim((string) ($this->templates[$event->value] ?? ''));

        return $template !== '' ? $template : $event->defaultTemplate($this->smsLanguage());
    }

    /**
     * The language of the school's default SMS templates.
     */
    public function smsLanguage(): SmsLanguage
    {
        return $this->language instanceof SmsLanguage ? $this->language : (SmsLanguage::tryFrom((string) $this->language) ?? SmsLanguage::Bangla);
    }

    /**
     * The event's template with its placeholders filled in.
     *
     * @param  array<string, string|int|float|null>  $values  Placeholder name (without braces) => value.
     */
    public function render(SmsEvent $event, array $values): string
    {
        $replacements = ['{school}' => (string) SchoolSetting::get('school_name', '')];

        foreach ($values as $key => $value) {
            $replacements['{'.$key.'}'] = (string) $value;
        }

        return trim(preg_replace('/\s+/u', ' ', strtr($this->template($event), $replacements)));
    }

    public function ratePerSms(): float
    {
        return (float) $this->rate_per_sms;
    }

    public function currentBalance(): float
    {
        return (float) $this->balance;
    }

    /**
     * How many SMS parts the given amount (default: the current balance) pays for.
     */
    public function smsCountFor(?float $amount = null): int
    {
        $rate = $this->ratePerSms();

        if ($rate <= 0) {
            return 0;
        }

        // A tiny epsilon keeps 10.00 / 0.25 from flooring to 39 on float error.
        return (int) floor((($amount ?? $this->currentBalance()) / $rate) + 1e-9);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['is_enabled', 'rate_per_sms', 'enabled_events', 'templates'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('sms_setting')
            ->setDescriptionForEvent(fn (string $eventName): string => ucfirst($eventName).' SMS settings.');
    }
}
