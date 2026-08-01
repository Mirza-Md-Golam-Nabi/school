<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SalaryStructure extends Model
{
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
}
