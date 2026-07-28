<?php

namespace App\Actions\Concerns;

use App\Models\SalaryInvoice;

trait GeneratesSalaryInvoiceNumber
{
    /**
     * Generate the next invoice number for the given month/year. Sequence resets every month.
     */
    private function nextInvoiceNo(int $month, int $year): string
    {
        $prefix = sprintf('SAL-%d-%02d-', $year, $month);

        $sequence = SalaryInvoice::where('invoice_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->count();

        return $prefix.str_pad((string) ($sequence + 1), 4, '0', STR_PAD_LEFT);
    }
}
