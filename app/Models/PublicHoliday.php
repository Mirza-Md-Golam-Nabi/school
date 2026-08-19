<?php

namespace App\Models;

use App\Enums\PublicHolidayType;
use Database\Factories\PublicHolidayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PublicHoliday extends Model
{
    /** @use HasFactory<PublicHolidayFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = ['name', 'type', 'start_date', 'end_date', 'is_recurring', 'description'];

    protected $casts = [
        'type' => PublicHolidayType::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'is_recurring' => 'boolean',
    ];

    public function isRange(): bool
    {
        return $this->type === PublicHolidayType::Range;
    }

    public function getDurationInDaysAttribute(): int
    {
        if (! $this->isRange() || $this->end_date === null) {
            return 1;
        }

        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('public_holiday')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    private function activityLogDescription(string $eventName): string
    {
        if ($eventName === 'created') {
            $typeLabel = $this->type?->getLabel() ?? (string) $this->type;
            $dateLabel = $this->isRange() && $this->end_date
                ? "{$this->start_date?->format('Y-m-d')} to {$this->end_date->format('Y-m-d')}"
                : (string) $this->start_date?->format('Y-m-d');

            return "Created public holiday \"{$this->name}\" ({$typeLabel}, {$dateLabel}).";
        }

        return ucfirst($eventName)." public holiday \"{$this->name}\".";
    }
}
