<?php

namespace App\Actions;

use App\Actions\Concerns\ResolvesFeeDiscount;
use App\Enums\InvoiceStatus;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Notifications\Concerns\NotifiesStudentFeeInvoice;
use Illuminate\Support\Collection;

class CollectPromotionFeesAction
{
    use NotifiesStudentFeeInvoice;
    use ResolvesFeeDiscount;

    /**
     * Available structures per "class-session", remembered for the lifetime
     * of this instance — the promotion form asks for them many times per render.
     *
     * @var array<string, Collection<int, FeeStructure>>
     */
    private array $structures = [];

    public function __construct(private ProcessFeePaymentAction $processFeePayment) {}

    /**
     * Target ক্লাস ও নতুন সেশনের সব active fee structure — promote মডালের
     * ড্রপডাউন আর handle() দুটোই এখান থেকে নেয়, যাতে অন্য ক্লাস/সেশনের
     * structure id পাঠিয়ে ইনভয়েস তৈরি করা না যায়।
     *
     * @return Collection<int, FeeStructure>
     */
    public function availableStructures(?int $classId, int $sessionYear): Collection
    {
        if (! $classId) {
            return collect();
        }

        return $this->structures["{$classId}-{$sessionYear}"] ??= FeeStructure::with('feeType:id,name,is_monthly')
            ->where('class_id', $classId)
            ->where('session_year', $sessionYear)
            ->where('is_active', true)
            ->whereIn('fee_type_id', FeeType::where('is_active', true)->select('id'))
            ->get()
            ->sortBy(fn (FeeStructure $structure): string => $structure->feeType->name)
            ->values();
    }

    /**
     * নির্বাচিত ফি-গুলোর মোট বকেয়া — discount বাদ দিয়ে, আর আগে থেকে থাকা
     * ইনভয়েসে যা পরিশোধ হয়েছে সেটাও বাদ দিয়ে।
     *
     * @param  array<int, int|string>  $feeStructureIds
     * @param  array<int, int|string>  $months
     */
    public function payableTotal(StudentProfile $student, int $classId, int $sessionYear, array $feeStructureIds, array $months): float
    {
        return (float) $this->resolveLines($student, $classId, $sessionYear, $feeStructureIds, $months)->sum('due');
    }

    /**
     * নির্বাচিত প্রতিটা ফি-র ইনভয়েস তৈরি করে (আগে থেকে থাকলে সেটাই ব্যবহার
     * হয়), তারপর দেওয়া টাকা সেই ইনভয়েসগুলোর বিপরীতে জমা করে। টাকা না দিলে
     * বা কম দিলে ইনভয়েসগুলো unpaid/partial হিসেবে due থেকে যায়।
     *
     * @param  array<int, int|string>  $feeStructureIds
     * @param  array<int, int|string>  $months
     * @param  array{amount_paid?: float|string|null, receipt_no?: ?string, payment_method?: mixed, payment_date?: mixed, transaction_id?: ?string, received_by?: ?int, remarks?: ?string}  $payment
     */
    public function handle(StudentProfile $student, int $classId, int $sessionYear, array $feeStructureIds, array $months, array $payment): ?FeePayment
    {
        $lines = $this->resolveLines($student, $classId, $sessionYear, $feeStructureIds, $months);

        $payableInvoiceIds = $lines->map(function (array $line) use ($student, $sessionYear): ?int {
            if ($line['invoice']) {
                return $line['due'] > 0 ? $line['invoice']->id : null;
            }

            $invoice = StudentFeeInvoice::create([
                'student_id' => $student->id,
                'fee_type_id' => $line['structure']->fee_type_id,
                'month' => $line['month'],
                'year' => $sessionYear,
                'original_amount' => $line['structure']->amount,
                'discount_amount' => $line['discount'],
                'fine_amount' => 0,
                'waiver_amount' => 0,
                'net_amount' => $line['due'],
                'status' => InvoiceStatus::Unpaid,
            ]);

            $this->notifyFeeInvoiceGenerated($invoice, $student->user);

            return $line['due'] > 0 ? $invoice->id : null;
        })->filter()->values();

        $amountPaid = min((float) ($payment['amount_paid'] ?? 0), (float) $lines->sum('due'));

        if ($amountPaid <= 0) {
            return null;
        }

        return $this->processFeePayment->handle([
            'invoice_ids' => $payableInvoiceIds->all(),
            'amount_paid' => $amountPaid,
            'receipt_no' => $payment['receipt_no'],
            'student_id' => $student->id,
            'payment_method' => $payment['payment_method'],
            'transaction_id' => $payment['transaction_id'] ?? null,
            'payment_date' => $payment['payment_date'],
            'received_by' => $payment['received_by'] ?? null,
            'remarks' => $payment['remarks'] ?? null,
        ]);
    }

    /**
     * One-time ফি-র জন্য একটা লাইন (month null), মাসিক ফি-র জন্য নির্বাচিত
     * প্রতিটা মাসে একটা করে লাইন।
     *
     * @param  array<int, int|string>  $feeStructureIds
     * @param  array<int, int|string>  $months
     * @return Collection<int, array{structure: FeeStructure, month: ?int, invoice: ?StudentFeeInvoice, discount: float, due: float}>
     */
    private function resolveLines(StudentProfile $student, int $classId, int $sessionYear, array $feeStructureIds, array $months): Collection
    {
        $structures = $this->availableStructures($classId, $sessionYear)
            ->whereIn('id', array_map('intval', $feeStructureIds));

        if ($structures->isEmpty()) {
            return collect();
        }

        $months = collect($months)
            ->map(fn (int|string $month): int => (int) $month)
            ->filter(fn (int $month): bool => $month >= 1 && $month <= 12)
            ->unique()
            ->sort()
            ->values();

        $existingInvoices = StudentFeeInvoice::with('payments')
            ->where('student_id', $student->id)
            ->where('year', $sessionYear)
            ->whereIn('fee_type_id', $structures->pluck('fee_type_id'))
            ->get()
            ->keyBy(fn (StudentFeeInvoice $invoice): string => $invoice->fee_type_id.'-'.($invoice->month ?? 0));

        return $structures->flatMap(function (FeeStructure $structure) use ($student, $sessionYear, $months, $existingInvoices): array {
            $periods = $structure->feeType->is_monthly ? $months->all() : [null];

            return array_map(function (?int $month) use ($structure, $student, $sessionYear, $existingInvoices): array {
                $invoice = $existingInvoices->get($structure->fee_type_id.'-'.($month ?? 0));

                if ($invoice) {
                    $due = $invoice->status === InvoiceStatus::Waived
                        ? 0.0
                        : max(0, (float) $invoice->net_amount - (float) $invoice->payments->sum('amount_paid'));

                    return ['structure' => $structure, 'month' => $month, 'invoice' => $invoice, 'discount' => (float) $invoice->discount_amount, 'due' => $due];
                }

                $discount = $this->resolveDiscount($student, $structure->fee_type_id, (float) $structure->amount, $sessionYear);

                return [
                    'structure' => $structure,
                    'month' => $month,
                    'invoice' => null,
                    'discount' => $discount,
                    'due' => max(0, (float) $structure->amount - $discount),
                ];
            }, $periods);
        })->values();
    }
}
