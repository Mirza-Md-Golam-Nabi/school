<?php

namespace App\Http\Controllers;

use App\Models\FeePayment;
use App\Models\SchoolSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * The public page a payment slip's QR code (or its typed-in code) opens: what
 * the school's books hold for that slip right now — fee lines and amounts — so
 * a forged or altered receipt shows a mismatch. A slip whose payments were all
 * deleted is no longer valid. Contact details are never shown.
 */
class VerifyFeeReceiptController extends Controller
{
    /**
     * A payment touched this long after it was created counts as revised.
     */
    private const REVISION_GRACE_SECONDS = 60;

    public function __invoke(string $code): Response
    {
        $payments = FeePayment::findSlipByVerificationCode($code)
            ->load(['student.user', 'student.class', 'student.section', 'student.group', 'invoice.feeType', 'receivedBy']);

        $lastRevisedAt = $payments
            ->filter(fn (FeePayment $payment): bool => $payment->updated_at && $payment->created_at
                && $payment->created_at->diffInSeconds($payment->updated_at) > self::REVISION_GRACE_SECONDS)
            ->max('updated_at');

        $logo = SchoolSetting::get('school_logo');

        return response()->view('verify-fee-receipt', [
            'payments' => $payments,
            'lastRevisedAt' => $lastRevisedAt,
            'schoolName' => (string) config('app.school_name'),
            'schoolLogoUrl' => filled($logo) ? Storage::disk('public')->url($logo) : null,
        ], $payments->isNotEmpty() ? 200 : 404);
    }
}
