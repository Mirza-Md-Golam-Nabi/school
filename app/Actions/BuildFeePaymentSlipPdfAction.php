<?php

namespace App\Actions;

use App\Models\FeePayment;
use App\Support\Concerns\BuildsMpdfDocuments;
use Illuminate\Database\Eloquent\Collection;

class BuildFeePaymentSlipPdfAction
{
    use BuildsMpdfDocuments;

    /**
     * Render every fee payment collected under one batch (a single-invoice
     * payment, or several invoices paid together in one submission) as one
     * combined "Payment Slip" PDF.
     *
     * @param  Collection<int, FeePayment>  $payments
     */
    public function handle(Collection $payments): string
    {
        $mpdf = $this->makeMpdf('A5');

        $html = view('documents.fee-payment-slip', ['payments' => $payments])->render();

        $mpdf->WriteHTML($html);

        return $this->outputMpdfString($mpdf);
    }
}
