<?php

namespace App\Models;

use App\Enums\DeviceUserRemovalStatus;
use App\Enums\EmploymentStatus;
use App\Enums\StudentStatus;
use Database\Factories\DeviceUserFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DeviceUser extends Model
{
    /** @use HasFactory<DeviceUserFactory> */
    use HasFactory;

    protected $fillable = [
        'attendance_device_id',
        'enroll_id',
        'enrollable_type',
        'enrollable_id',
        'card_number',
        'fingerprint_count',
        'removal_status',
        'removal_due_at',
        'removed_at',
        'last_seen_on_device_at',
    ];

    protected $casts = [
        'fingerprint_count' => 'integer',
        'removal_status' => DeviceUserRemovalStatus::class,
        'removal_due_at' => 'datetime',
        'removed_at' => 'datetime',
        'last_seen_on_device_at' => 'datetime',
    ];

    /**
     * Enrollments still on the device's roster (not yet removed). Removed rows are
     * kept forever so their enroll ID is never handed to someone else and their old
     * punches still resolve to the right person.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('removed_at');
    }

    /**
     * Enrollments whose removal is scheduled and whose grace period is over, so the
     * sync client should now delete them from the device.
     */
    #[Scope]
    protected function readyForRemoval(Builder $query): void
    {
        $query->whereNull('removed_at')
            ->where('removal_status', DeviceUserRemovalStatus::Queued)
            ->where('removal_due_at', '<=', now());
    }

    /**
     * The kinds of people that can be enrolled on a device, keyed by model class.
     *
     * @return array<class-string<Model>, string>
     */
    public static function personTypes(): array
    {
        return [
            StudentProfile::class => 'Student',
            TeacherProfile::class => 'Teacher',
            StaffProfile::class => 'Staff',
        ];
    }

    /**
     * A short human label for a profile, e.g. "Rahim (Class 5, Roll 3)".
     */
    public static function describePerson(Model $person): string
    {
        $name = $person->user?->name ?? "#{$person->getKey()}";

        if ($person instanceof StudentProfile) {
            return "{$name} (".($person->class?->display_name ?? 'No class').", Roll {$person->roll_no})";
        }

        return $name;
    }

    /**
     * Whether this profile should currently be on attendance devices: not deleted
     * and with an active student/employment status.
     */
    public static function isActivePerson(Model $person): bool
    {
        if (method_exists($person, 'trashed') && $person->trashed()) {
            return false;
        }

        // A freshly created model doesn't carry column defaults yet.
        $status = $person->status ?? $person->fresh()?->status;

        return $status === StudentStatus::Active || $status === EmploymentStatus::Active;
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    /**
     * The StudentProfile / TeacherProfile / StaffProfile this enroll ID belongs to.
     */
    public function enrollable(): MorphTo
    {
        return $this->morphTo();
    }

    public function personTypeLabel(): string
    {
        return self::personTypes()[$this->enrollable_type] ?? 'Unknown';
    }

    public function personLabel(): string
    {
        return $this->enrollable ? self::describePerson($this->enrollable) : 'Deleted profile';
    }

    /**
     * Where this enrollment stands, for display.
     */
    public function stateLabel(): string
    {
        if ($this->isRemoved()) {
            return 'Removed';
        }

        if ($this->removal_status !== null) {
            return $this->removal_status->getLabel();
        }

        return $this->last_seen_on_device_at === null ? 'Not on device yet' : 'On device';
    }

    public function isRemoved(): bool
    {
        return $this->removed_at !== null;
    }

    /**
     * Starts removing this person from the device. Someone who never reached the
     * device is simply marked removed. Otherwise the removal is either scheduled
     * (after a grace period, so a wrong status change can still be undone) or, when
     * $needsApproval is set, parked until an admin approves it.
     */
    public function requestRemoval(bool $needsApproval, ?int $graceHours = null): void
    {
        if ($this->isRemoved()) {
            return;
        }

        if ($this->last_seen_on_device_at === null) {
            $this->markRemoved();

            return;
        }

        if ($needsApproval) {
            if ($this->removal_status === null) {
                $this->forceFill(['removal_status' => DeviceUserRemovalStatus::PendingApproval, 'removal_due_at' => null])->save();
            }

            return;
        }

        if ($this->removal_status === DeviceUserRemovalStatus::Queued) {
            return;
        }

        $this->forceFill([
            'removal_status' => DeviceUserRemovalStatus::Queued,
            'removal_due_at' => now()->addHours($graceHours ?? config('attendance.removal_grace_hours')),
        ])->save();
    }

    public function approveRemoval(): void
    {
        if ($this->removal_status !== DeviceUserRemovalStatus::PendingApproval) {
            return;
        }

        if ($this->last_seen_on_device_at === null) {
            $this->markRemoved();

            return;
        }

        $this->forceFill(['removal_status' => DeviceUserRemovalStatus::Queued, 'removal_due_at' => now()])->save();
    }

    public function cancelRemoval(): void
    {
        if ($this->isRemoved() || $this->removal_status === null) {
            return;
        }

        $this->forceFill(['removal_status' => null, 'removal_due_at' => null])->save();
    }

    public function markRemoved(): void
    {
        $this->forceFill([
            'removed_at' => now(),
            'removal_status' => null,
            'removal_due_at' => null,
            'card_number' => null,
            'fingerprint_count' => 0,
            'last_seen_on_device_at' => null,
        ])->save();
    }

    /**
     * Puts a removed person back on the roster under the same enroll ID. The sync
     * client re-creates them on the device, where they must re-register their card
     * or fingerprint.
     */
    public function restoreToRoster(): void
    {
        $this->forceFill([
            'removed_at' => null,
            'removal_status' => null,
            'removal_due_at' => null,
            'card_number' => null,
            'fingerprint_count' => 0,
            'last_seen_on_device_at' => null,
        ])->save();
    }
}
