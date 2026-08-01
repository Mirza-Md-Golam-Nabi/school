<?php

namespace App\Models;

use App\Enums\SalaryComponentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryInvoiceComponent extends Model
{
    protected $fillable = [
        'salary_invoice_id',
        'salary_component_id',
        'name',
        'type',
        'amount',
    ];

    protected $casts = [
        'type' => SalaryComponentType::class,
        'amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalaryInvoice::class, 'salary_invoice_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }
}
