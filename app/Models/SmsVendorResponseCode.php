<?php

namespace App\Models;

use App\Enums\SmsVendorDriver;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * What a provider's API response code means. Codes belong to the provider
 * (driver), so every vendor account of the same provider shares them.
 */
class SmsVendorResponseCode extends Model
{
    use LogsActivity;

    protected $fillable = [
        'driver',
        'code',
        'message',
        'is_success',
    ];

    protected $casts = [
        'driver' => SmsVendorDriver::class,
        'is_success' => 'boolean',
    ];

    public static function lookup(SmsVendorDriver $driver, string|int|null $code): ?self
    {
        if ($code === null || $code === '') {
            return null;
        }

        return self::query()->where('driver', $driver)->where('code', (string) $code)->first();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('sms_vendor_response_code')
            ->setDescriptionForEvent(fn (string $eventName): string => ucfirst($eventName)." SMS response code {$this->code}.");
    }
}
