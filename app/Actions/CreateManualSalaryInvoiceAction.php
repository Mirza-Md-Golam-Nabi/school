<?php

namespace App\Actions;

use App\Actions\Concerns\GeneratesSalaryInvoiceNumber;
use App\Enums\InvoiceStatus;
use App\Models\SalaryInvoice;
use Illuminate\Support\Facades\DB;

class CreateManualSalaryInvoiceAction
{
    use GeneratesSalaryInvoiceNumber;

    /**
     * Manually create a single salary invoice with a flat, admin-entered net amount.
     * Used for cases the automatic monthly generation doesn't cover (mid-month
     * joiners, resigned/inactive staff, corrections, etc). No component breakdown
     * is recorded — this is a flat amount, not structure-derived.
     */
    public function handle(string $profileableType, int $profileableId, int $month, int $year, float $netAmount, ?int $createdBy = null): SalaryInvoice
    {
        $alreadyExists = SalaryInvoice::where('profileable_type', $profileableType)
            ->where('profileable_id', $profileableId)
            ->where('month', $month)
            ->where('year', $year)
            ->exists();

        if ($alreadyExists) {
            throw new \RuntimeException('এই মাসের জন্য ইতিমধ্যে একটা invoice আছে।');
        }

        return DB::transaction(fn () => SalaryInvoice::create([
            'invoice_no' => $this->nextInvoiceNo($month, $year),
            'profileable_type' => $profileableType,
            'profileable_id' => $profileableId,
            'salary_structure_id' => null,
            'month' => $month,
            'year' => $year,
            'gross_amount' => $netAmount,
            'deduction_amount' => 0,
            'net_amount' => $netAmount,
            'status' => InvoiceStatus::Unpaid,
            'is_manual' => true,
            'created_by' => $createdBy,
        ]));
    }
}
