<?php

namespace App\Models;

use App\Enums\AttendanceDeviceDriver;
use Database\Factories\AttendanceDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AttendanceDevice extends Model
{
    /** @use HasFactory<AttendanceDeviceFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'name',
        'serial_number',
        'driver',
        'api_token_hash',
        'is_active',
        'last_synced_at',
        'reported_sizes',
        'sizes_reported_at',
        'unknown_device_users',
    ];

    protected $hidden = ['api_token_hash'];

    protected $casts = [
        'driver' => AttendanceDeviceDriver::class,
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        'reported_sizes' => 'array',
        'sizes_reported_at' => 'datetime',
        'unknown_device_users' => 'array',
    ];

    /**
     * Enroll IDs are numeric and only ever count up — removed enrollments still count,
     * so an ID is never handed to a second person.
     */
    public function nextFreeEnrollId(): int
    {
        $highest = $this->deviceUsers()
            ->pluck('enroll_id')
            ->filter(fn (string $enrollId): bool => ctype_digit($enrollId))
            ->map(fn (string $enrollId): int => (int) $enrollId)
            ->max();

        return ($highest ?? 0) + 1;
    }

    /**
     * How full one of the device's stores is, from the last size report.
     *
     * @param  'users'|'fingers'|'cards'|'records'  $store
     * @return array{used: int, limit: int, percent: int}|null
     */
    public function usageOf(string $store): ?array
    {
        $used = $this->reported_sizes[$store] ?? null;
        $limit = $this->reported_sizes["{$store}_cap"] ?? null;

        if ($used === null || ! $limit) {
            return null;
        }

        return ['used' => (int) $used, 'limit' => (int) $limit, 'percent' => (int) round($used / $limit * 100)];
    }

    /**
     * One-line capacity report, e.g. "Users 320/1000 · Fingerprints 610/3000 · ...".
     */
    public function capacitySummary(): ?string
    {
        $labels = ['users' => 'Users', 'fingers' => 'Fingerprints', 'cards' => 'Cards', 'records' => 'Records'];

        $parts = collect($labels)
            ->map(fn (string $label, string $store): ?string => ($usage = $this->usageOf($store))
                ? "{$label} {$usage['used']}/{$usage['limit']} ({$usage['percent']}%)"
                : null)
            ->filter();

        if ($parts->isEmpty()) {
            return null;
        }

        return $parts->implode(' · ').' — reported '.$this->sizes_reported_at?->diffForHumans();
    }

    public function deviceUsers(): HasMany
    {
        return $this->hasMany(DeviceUser::class);
    }

    public function punches(): HasMany
    {
        return $this->hasMany(AttendancePunch::class);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findActiveByToken(string $token): ?self
    {
        return static::query()
            ->where('api_token_hash', static::hashToken($token))
            ->where('is_active', true)
            ->first();
    }

    /**
     * Issues a fresh API token, stores only its hash and returns the plain
     * token — it can never be retrieved again, so the caller must show it once.
     */
    public function rotateToken(): string
    {
        $token = Str::random(48);

        $this->forceFill(['api_token_hash' => static::hashToken($token)])->save();

        return $token;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'serial_number', 'driver', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('attendance_device')
            ->setDescriptionForEvent(fn (string $eventName): string => ucfirst($eventName)." attendance device \"{$this->name}\".");
    }
}
