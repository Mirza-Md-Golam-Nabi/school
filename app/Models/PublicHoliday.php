<?php

namespace App\Models;

use App\Enums\PublicHolidayType;
use Database\Factories\PublicHolidayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PublicHoliday extends Model
{
    /** @use HasFactory<PublicHolidayFactory> */
    use HasFactory;

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
}
