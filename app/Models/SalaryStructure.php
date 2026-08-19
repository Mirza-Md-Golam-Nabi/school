<?php

namespace App\Models;

use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SalaryStructure extends Model
{
    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'profileable_type',
        'profileable_id',
        'use_components',
        'flat_amount',
        'effective_from',
        'effective_to',
        'created_by',
    ];

    protected $casts = [
        'use_components' => 'boolean',
        'flat_amount' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    /**
     * A person should only have one open-ended ("চলমান") structure at a time.
     * When a new one is created, close out any previous open structure for the
     * same profileable the day before the new one takes effect, so invoice
     * generation and the structures table both reflect a clean history instead
     * of two rows claiming to be simultaneously ongoing.
     */
    protected static function booted(): void
    {
        static::created(function (SalaryStructure $structure) {
            static::where('profileable_type', $structure->profileable_type)
                ->where('profileable_id', $structure->profileable_id)
                ->whereKeyNot($structure->id)
                ->whereNull('effective_to')
                ->where('effective_from', '<', $structure->effective_from)
                ->get()
                ->each(fn (SalaryStructure $previous) => $previous->update([
                    'effective_to' => $structure->effective_from->copy()->subDay()->toDateString(),
                ]));
        });
    }

    public function profileable(): MorphTo
    {
        return $this->morphTo();
    }

    public function components(): HasMany
    {
        return $this->hasMany(SalaryStructureComponent::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SalaryInvoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    #[Scope]
    protected function effectiveOn(Builder $query, string $date): void
    {
        $query->where('effective_from', '<=', $date)
            ->where(function (Builder $query) use ($date) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('salary_structure')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'created_by' => fn (int|string|null $id): ?string => $id === null ? null : User::find($id)?->name,
        ];
    }

    private function activityLogDescription(string $eventName): string
    {
        $personLabel = $this->resolveProfileableLabel();

        if ($eventName === 'created') {
            $amountLabel = $this->use_components
                ? 'component-based amounts'
                : '৳'.number_format((float) $this->flat_amount, 2).' flat';
            $periodLabel = $this->effective_to
                ? "{$this->effective_from?->format('Y-m-d')} to {$this->effective_to->format('Y-m-d')}"
                : "from {$this->effective_from?->format('Y-m-d')}";

            return "Created salary structure for \"{$personLabel}\" ({$amountLabel}, {$periodLabel}).";
        }

        $changedFillableKeys = array_values(array_intersect(array_keys($this->getChanges()), $this->getFillable()));

        if ($eventName === 'updated' && $changedFillableKeys === ['effective_to'] && $this->effective_to !== null) {
            return "Closed out salary structure for \"{$personLabel}\" (effective to {$this->effective_to->format('Y-m-d')}) — a newer structure now applies.";
        }

        return ucfirst($eventName)." salary structure for \"{$personLabel}\".";
    }

    private function resolveProfileableLabel(): string
    {
        $profileable = $this->profileable;

        if ($profileable instanceof TeacherProfile || $profileable instanceof StaffProfile) {
            return $profileable->user?->name ?? class_basename($profileable)." #{$this->profileable_id}";
        }

        return "#{$this->profileable_id}";
    }
}
