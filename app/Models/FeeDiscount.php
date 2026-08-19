<?php

namespace App\Models;

use App\Enums\FeeDiscountType;
use App\Traits\LogsRelationLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FeeDiscount extends Model
{
    use LogsActivity;
    use LogsRelationLabels;

    protected $fillable = [
        'name',
        'discount_type',
        'discount_value',
    ];

    protected $casts = [
        'discount_type' => FeeDiscountType::class,
        'discount_value' => 'decimal:2',
    ];

    public function studentFeeDiscounts(): HasMany
    {
        return $this->hasMany(StudentFeeDiscount::class, 'discount_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('fee_discount')
            ->setDescriptionForEvent(fn (string $eventName): string => ucfirst($eventName)." fee discount \"{$this->name}\".");
    }

    protected function activityLogRelationLabels(): array
    {
        return [
            'discount_type' => fn (?string $value): ?string => $value === null ? null : FeeDiscountType::tryFrom($value)?->getLabel(),
        ];
    }
}
