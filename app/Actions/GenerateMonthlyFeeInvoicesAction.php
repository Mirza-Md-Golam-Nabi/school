<?php

namespace App\Actions;

use App\Actions\Concerns\ResolvesFeeDiscount;
use App\Actions\Sms\SendFeeInvoiceSmsAction;
use App\Enums\InvoiceStatus;
use App\Models\Classes;
use App\Models\FeeStructure;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Notifications\Concerns\NotifiesStudentFeeInvoice;
use App\Support\AcademicSession;
use Carbon\Carbon;

class GenerateMonthlyFeeInvoicesAction
{
    use NotifiesStudentFeeInvoice;
    use ResolvesFeeDiscount;

    public function __construct(private SendFeeInvoiceSmsAction $sendFeeInvoiceSms) {}

    /**
     * Generate monthly fee invoices for active students based on their class fee structures.
     * Existing invoices for the same student/fee type/month/year are skipped.
     *
     * The month/year is a calendar month; the fee structure and discounts are
     * looked up by the session that month falls in for each class — e.g.
     * March 2027 is session 2026 (2026-27) for a college class.
     *
     * @return array{generated: int, skipped: int}
     */
    public function handle(int $month, int $year, ?int $classId = null): array
    {
        $classesQuery = Classes::active();

        if ($classId) {
            $classesQuery->where('id', $classId);
        }

        $classes = $classesQuery->get(['id', 'level']);
        $firstDayOfMonth = Carbon::create($year, $month, 1);

        $generated = 0;
        $skipped = 0;
        $newInvoices = [];

        foreach ($classes as $class) {
            $sessionYear = AcademicSession::yearForDate($firstDayOfMonth, $class);

            $structures = FeeStructure::where('class_id', $class->id)
                ->where('session_year', $sessionYear)
                ->where('is_active', true)
                ->whereHas('feeType', fn ($q) => $q->where('is_monthly', true)->where('is_active', true))
                ->get(['fee_type_id', 'amount']);

            if ($structures->isEmpty()) {
                continue;
            }

            $students = StudentProfile::with([
                'feeDiscounts:student_id,fee_type_id,session_year,discount_id',
                'feeDiscounts.discount:id,discount_type,discount_value',
                'user',
            ])
                ->active()
                ->where('current_class_id', $class->id)
                ->get(['id', 'user_id', 'name_bn', 'student_id_no', 'guardian_phone', 'current_class_id']);

            if ($students->isEmpty()) {
                continue;
            }

            $existingSet = StudentFeeInvoice::where('month', $month)
                ->where('year', $year)
                ->whereIn('student_id', $students->pluck('id'))
                ->get(['student_id', 'fee_type_id'])
                ->mapWithKeys(fn ($inv) => ["{$inv->student_id}-{$inv->fee_type_id}" => true])
                ->all();

            foreach ($students as $student) {
                foreach ($structures as $structure) {
                    $key = "{$student->id}-{$structure->fee_type_id}";

                    if (isset($existingSet[$key])) {
                        $skipped++;

                        continue;
                    }

                    $discountAmount = $this->resolveDiscount($student, $structure->fee_type_id, (float) $structure->amount, $sessionYear);
                    $netAmount = max(0, $structure->amount - $discountAmount);

                    $invoice = StudentFeeInvoice::create([
                        'student_id' => $student->id,
                        'fee_type_id' => $structure->fee_type_id,
                        'month' => $month,
                        'year' => $year,
                        'original_amount' => $structure->amount,
                        'discount_amount' => $discountAmount,
                        'fine_amount' => 0,
                        'waiver_amount' => 0,
                        'net_amount' => $netAmount,
                        'status' => InvoiceStatus::Unpaid,
                    ]);

                    $generated++;

                    $this->notifyFeeInvoiceGenerated($invoice, $student->user);

                    $newInvoices[] = $invoice->setRelation('student', $student);
                }
            }

            unset($students, $structures, $existingSet);
        }

        $this->logGeneration($month, $year, $classId, $generated, $skipped);

        $this->sendFeeInvoiceSms->handle($newInvoices);

        return ['generated' => $generated, 'skipped' => $skipped];
    }

    private function logGeneration(int $month, int $year, ?int $classId, int $generated, int $skipped): void
    {
        $scopeLabel = $classId
            ? (Classes::find($classId)?->name ?? "Class #{$classId}")
            : 'All Classes';

        $periodLabel = Carbon::create()->month($month)->format('F').' '.$year;

        activity('fee_invoice_generation')
            ->event('generated')
            ->withProperties([
                'attributes' => [
                    'class_id' => $classId,
                    'class_id_label' => $classId ? $scopeLabel : null,
                    'month' => $month,
                    'year' => $year,
                    'generated_count' => $generated,
                    'skipped_count' => $skipped,
                ],
            ])
            ->log("Generated {$generated} monthly fee invoice(s) for {$scopeLabel} ({$periodLabel}) — {$skipped} skipped (already invoiced).");
    }
}
