<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TransactionCategory extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'type',
        'is_active',
    ];

    protected $casts = [
        'type' => TransactionType::class,
        'is_active' => 'boolean',
    ];

    public function fundTransactions(): HasMany
    {
        return $this->hasMany(FundTransaction::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('transaction_category')
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityLogDescription($eventName));
    }

    private function activityLogDescription(string $eventName): string
    {
        if ($eventName === 'created') {
            $typeLabel = $this->type?->getLabel() ?? (string) $this->type;

            return "Created transaction category \"{$this->name}\" ({$typeLabel}).";
        }

        return ucfirst($eventName)." transaction category \"{$this->name}\".";
    }
}
