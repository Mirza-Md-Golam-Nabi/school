<?php

namespace App\Models;

use App\Enums\FeeDiscountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeDiscount extends Model
{
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
}
