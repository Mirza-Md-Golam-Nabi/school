<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryInvoiceDeduction extends Model
{
    protected $fillable = [
        'salary_invoice_id',
        'salary_component_id',
        'amount',
        'reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (SalaryInvoiceDeduction $deduction): void {
            if ($deduction->invoice?->isLocked()) {
                throw new \RuntimeException('Payment থাকা অবস্থায় invoice-এ নতুন deduction যোগ করা যাবে না।');
            }
        });

        static::deleting(function (SalaryInvoiceDeduction $deduction): void {
            if ($deduction->invoice?->isLocked()) {
                throw new \RuntimeException('Payment থাকা অবস্থায় invoice-এর deduction বাদ দেওয়া যাবে না।');
            }
        });

        static::saved(fn (SalaryInvoiceDeduction $deduction) => $deduction->recalculateInvoiceTotals());
        static::deleted(fn (SalaryInvoiceDeduction $deduction) => $deduction->recalculateInvoiceTotals());
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalaryInvoice::class, 'salary_invoice_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }

    protected function recalculateInvoiceTotals(): void
    {
        $invoice = $this->invoice;

        if (! $invoice) {
            return;
        }

        $deductionAmount = (float) $invoice->deductions()->sum('amount');
        $netAmount = max(0, (float) $invoice->gross_amount - $deductionAmount);

        $invoice->update([
            'deduction_amount' => $deductionAmount,
            'net_amount' => $netAmount,
        ]);
    }
}
