<?php

namespace App\Models;

use App\Enums\SalaryComponentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryComponent extends Model
{
    protected $fillable = [
        'name',
        'type',
        'is_active',
    ];

    protected $casts = [
        'type' => SalaryComponentType::class,
        'is_active' => 'boolean',
    ];

    public function structureComponents(): HasMany
    {
        return $this->hasMany(SalaryStructureComponent::class);
    }

    public function invoiceComponents(): HasMany
    {
        return $this->hasMany(SalaryInvoiceComponent::class);
    }
}
