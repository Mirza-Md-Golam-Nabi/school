<?php

namespace App\Actions;

use App\Actions\Concerns\ResolvesFeeDiscount;
use App\Enums\InvoiceStatus;
use App\Models\FeeStructure;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Notifications\Concerns\NotifiesStudentFeeInvoice;
use InvalidArgumentException;

class GenerateOneTimeFeeInvoicesAction
{
    use NotifiesStudentFeeInvoice;
    use ResolvesFeeDiscount;

    /**
     * Generate one-time (non-monthly) fee invoices for every active student in the
     * fee structure's class. Existing invoices for the same student/fee type/year
     * (month is always null for one-time fees) are skipped.
     *
     * @return array{generated: int, skipped: int}
     */
    public function handle(FeeStructure $structure): array
    {
        $feeType = $structure->feeType;

        if (! $feeType || $feeType->is_monthly) {
            throw new InvalidArgumentException('Fee structure must use a one-time (non-monthly) fee type.');
        }

        $year = $structure->session_year;

        $students = StudentProfile::with([
            'feeDiscounts:student_id,fee_type_id,session_year,discount_id',
            'feeDiscounts.discount:id,discount_type,discount_value',
            'user',
        ])
            ->active()
            ->where('current_class_id', $structure->class_id)
            ->get(['id', 'user_id']);

        if ($students->isEmpty()) {
            return ['generated' => 0, 'skipped' => 0];
        }

        $existingStudentIds = StudentFeeInvoice::where('fee_type_id', $structure->fee_type_id)
            ->whereNull('month')
            ->where('year', $year)
            ->whereIn('student_id', $students->pluck('id'))
            ->pluck('student_id')
            ->flip()
            ->all();

        $generated = 0;
        $skipped = 0;

        foreach ($students as $student) {
            if (isset($existingStudentIds[$student->id])) {
                $skipped++;

                continue;
            }

            $discountAmount = $this->resolveDiscount($student, $structure->fee_type_id, (float) $structure->amount, $year);
            $netAmount = max(0, $structure->amount - $discountAmount);

            $invoice = StudentFeeInvoice::create([
                'student_id' => $student->id,
                'fee_type_id' => $structure->fee_type_id,
                'month' => null,
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
        }

        $this->logGeneration($structure, $generated, $skipped);

        return ['generated' => $generated, 'skipped' => $skipped];
    }

    private function logGeneration(FeeStructure $structure, int $generated, int $skipped): void
    {
        $classLabel = $structure->class?->name ?? "Class #{$structure->class_id}";
        $feeTypeLabel = $structure->feeType?->name ?? "Fee Type #{$structure->fee_type_id}";

        activity('fee_invoice_generation')
            ->performedOn($structure)
            ->event('generated')
            ->withProperties([
                'attributes' => [
                    'fee_structure_id' => $structure->id,
                    'class_id' => $structure->class_id,
                    'class_id_label' => $classLabel,
                    'fee_type_id' => $structure->fee_type_id,
                    'fee_type_id_label' => $feeTypeLabel,
                    'session_year' => $structure->session_year,
                    'generated_count' => $generated,
                    'skipped_count' => $skipped,
                ],
            ])
            ->log("Generated {$generated} one-time \"{$feeTypeLabel}\" invoice(s) for {$classLabel} ({$structure->session_year}) — {$skipped} skipped (already invoiced).");
    }
}
