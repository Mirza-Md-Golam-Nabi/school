<?php

namespace App\Models;

use App\Enums\FineType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LateFeeRule extends Model
{
    protected $fillable = [
        'fee_type_id',
        'class_id',
        'grace_days',
        'fine_type',
        'fine_value',
        'is_active',
    ];

    protected $casts = [
        'grace_days' => 'integer',
        'fine_type' => FineType::class,
        'fine_value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }
}
