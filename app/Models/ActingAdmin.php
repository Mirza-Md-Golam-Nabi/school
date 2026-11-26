<?php

namespace App\Models;

use App\Enums\ActingAdminLevel;
use App\Enums\EmploymentStatus;
use App\Traits\LogsRelationLabels;
use Database\Factories\ActingAdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ActingAdmin extends Model
{
    /** @use HasFactory<ActingAdminFactory> */
    use HasFactory;

    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'user_id',
        'assigned_by',
        'from_date',
        'to_date',
        'level',
        'is_active',
        'remarks',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'level' => ActingAdminLevel::class,
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * A null to_date means no end date — active permanently until manually removed.
     */
    public function isCurrentlyActive(): bool
    {
        return $this->is_active
        && $this->from_date->lte(Carbon::today())
        && ($this->to_date === null || $this->to_date->gte(Carbon::today()));
    }

    /**
     * Active teachers and staff as dropdown options (user id => "Name (Type)"),
     * sorted by name in the database. A user with both active profiles is
     * listed as Staff.
     *
     * @return array<int, string>
     */
    public static function activeUser(): array
    {
        $isActive = fn ($query) => $query->where('status', EmploymentStatus::Active);

        return User::query()
            ->where(fn ($query) => $query
                ->whereHas('teacherProfile', $isActive)
                ->orWhereHas('staffProfile', $isActive))
            ->withExists(['staffProfile as has_active_staff_profile' => $isActive])
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (User $user): array => [
                $user->id => $user->name.' ('.($user->has_active_staff_profile ? 'Staff' : 'Teacher').')',
            ])
            ->toArray();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('acting_admin')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'user_id' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(User::class, $id),
            'assigned_by' => fn (int|string|null $id): ?string => $id === null ? null : self::activityNameLabel(User::class, $id),
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $userLabel = $this->user?->name ?? "User #{$this->user_id}";
        $levelLabel = $this->level?->getLabel() ?? (string) $this->level;

        if ($eventName === 'created') {
            $periodLabel = $this->to_date
                ? "{$this->from_date?->format('Y-m-d')} to {$this->to_date->format('Y-m-d')}"
                : "from {$this->from_date?->format('Y-m-d')} (no end date)";

            return "Assigned \"{$userLabel}\" as {$levelLabel} ({$periodLabel}).";
        }

        return ucfirst($eventName)." acting admin assignment for \"{$userLabel}\" ({$levelLabel}).";
    }
}
