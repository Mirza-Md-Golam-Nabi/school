<?php

namespace App\Actions;

use App\Actions\Concerns\ResolvesFeeDiscount;
use App\Enums\InvoiceStatus;
use App\Models\FeeStructure;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\FeeInvoiceGeneratedNotification;
use InvalidArgumentException;

class GenerateOneTimeFeeInvoicesAction
{
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
        $now = now();
        $toInsert = [];
        $notificationData = [];

        foreach ($students as $student) {
            if (isset($existingStudentIds[$student->id])) {
                $skipped++;

                continue;
            }

            $discountAmount = $this->resolveDiscount($student, $structure->fee_type_id, (float) $structure->amount, $year);
            $netAmount = max(0, $structure->amount - $discountAmount);

            $toInsert[] = [
                'student_id' => $student->id,
                'fee_type_id' => $structure->fee_type_id,
                'month' => null,
                'year' => $year,
                'original_amount' => $structure->amount,
                'discount_amount' => $discountAmount,
                'fine_amount' => 0,
                'waiver_amount' => 0,
                'net_amount' => $netAmount,
                'status' => InvoiceStatus::Unpaid->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $generated++;

            if ($student->user_id) {
                $notificationData[$student->user_id] ??= ['count' => 0, 'total' => 0.0];
                $notificationData[$student->user_id]['count']++;
                $notificationData[$student->user_id]['total'] += $netAmount;
            }
        }

        foreach (array_chunk($toInsert, 500) as $chunk) {
            StudentFeeInvoice::insert($chunk);
        }

        if ($notificationData) {
            User::whereIn('id', array_keys($notificationData))
                ->get(['id'])
                ->each(function (User $user) use ($notificationData, $year) {
                    $data = $notificationData[$user->id];
                    $user->notify(new FeeInvoiceGeneratedNotification(
                        count: $data['count'],
                        totalAmount: $data['total'],
                        month: null,
                        year: $year,
                    ));
                });
        }

        return ['generated' => $generated, 'skipped' => $skipped];
    }
}
