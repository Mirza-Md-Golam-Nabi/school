<?php

namespace App\Models;

use App\Enums\PunchDirection;
use Database\Factories\AttendancePunchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePunch extends Model
{
    /** @use HasFactory<AttendancePunchFactory> */
    use HasFactory;

    protected $fillable = [
        'attendance_device_id',
        'enroll_id',
        'punched_at',
        'verify_type',
        'state',
        'processed_at',
    ];

    protected $casts = [
        'punched_at' => 'datetime',
        'processed_at' => 'datetime',
        'state' => 'integer',
    ];

    /**
     * The device's "state" for a punch made with Check-In / Check-Out selected.
     * Any other value (breaks, overtime, or none at all) says nothing about direction.
     */
    public const STATE_CHECK_IN = 0;

    public const STATE_CHECK_OUT = 1;

    /**
     * Which way the device says this punch went, if it says so at all.
     */
    public function direction(): ?PunchDirection
    {
        return match ($this->state) {
            self::STATE_CHECK_IN => PunchDirection::Entry,
            self::STATE_CHECK_OUT => PunchDirection::Exit,
            default => null,
        };
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->whereNull('processed_at');
    }
}
