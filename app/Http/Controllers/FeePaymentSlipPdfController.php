<?php

namespace App\Http\Controllers;

use App\Actions\BuildFeePaymentSlipPdfAction;
use App\Models\FeePayment;
use App\Support\Concerns\SanitizesFilenames;
use Illuminate\Http\Response;

class FeePaymentSlipPdfController extends Controller
{
    use SanitizesFilenames;

    /**
     * Stream every fee payment sharing this batch id as one combined "Payment Slip" PDF.
     */
    public function __invoke(string $batchId, BuildFeePaymentSlipPdfAction $action): Response
    {
        $payments = FeePayment::with(['student.user', 'student.class', 'student.section', 'student.group', 'invoice.feeType', 'receivedBy'])
            ->where('payment_batch_id', $batchId)
            ->orderBy('id')
            ->get();

        abort_if($payments->isEmpty(), 404);

        $pdf = $action->handle($payments);

        $firstPayment = $payments->first();
        $studentName = $this->sanitizeFilenameSegment($firstPayment->student->user?->name ?? 'student');
        $baseReceiptNo = preg_replace('#/\d+$#', '', $firstPayment->receipt_no);
        $filename = "payment-slip-{$studentName}-{$baseReceiptNo}.pdf";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }
}
