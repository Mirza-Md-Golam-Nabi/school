<?php

namespace App\Models;

use App\Enums\SmsVendorDriver;
use App\Support\Sms\SmsGateway;
use Database\Factories\SmsVendorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SmsVendor extends Model
{
    /** @use HasFactory<SmsVendorFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'name',
        'driver',
        'api_key',
        'sender_id',
        'priority',
        'is_active',
    ];

    protected $hidden = ['api_key'];

    protected $casts = [
        'driver' => SmsVendorDriver::class,
        'api_key' => 'encrypted',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Active vendors that can actually send, in the order they are tried.
     *
     * @param  Builder<SmsVendor>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereNotNull('api_key')
            ->whereNotNull('sender_id')
            ->where('sender_id', '!=', '')
            ->orderBy('priority')
            ->orderBy('id');
    }

    public function gateway(): SmsGateway
    {
        $class = $this->driver->gatewayClass();

        return new $class($this);
    }

    public function smsLogs(): HasMany
    {
        return $this->hasMany(SmsLog::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        // The API key is a secret — it is never written to the activity log.
        return LogOptions::defaults()
            ->logOnly(['name', 'driver', 'sender_id', 'priority', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('sms_vendor')
            ->setDescriptionForEvent(fn (string $eventName): string => ucfirst($eventName)." SMS vendor \"{$this->name}\".");
    }
}
