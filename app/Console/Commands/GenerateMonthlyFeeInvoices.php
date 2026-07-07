<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Classes;
use App\Models\FeeStructure;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\FeeInvoiceGeneratedNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fees:generate-monthly {--month= : Month number (1-12), defaults to current month} {--year= : Year, defaults to current year}')]
#[Description('Generate monthly fee invoices for all active students based on their class fee structures')]
class GenerateMonthlyFeeInvoices extends Command
{
    public function handle(): int
    {
        $month = (int) ($this->option('month') ?? now()->month);
        $year = (int) ($this->option('year') ?? now()->year);

        $this->info("Generating fee invoices for {$month}/{$year}...");

        $classes = Classes::active()->get(['id']);

        if ($classes->isEmpty()) {
            $this->info('No active classes found.');

            return self::SUCCESS;
        }

        $generated = 0;
        $skipped = 0;
        $now = now();

        foreach ($classes as $class) {
            $structures = FeeStructure::where('class_id', $class->id)
                ->where('session_year', $year)
                ->where('is_active', true)
                ->whereHas('feeType', fn ($q) => $q->where('is_monthly', true)->where('is_active', true))
                ->get(['fee_type_id', 'amount']);

            if ($structures->isEmpty()) {
                continue;
            }

            // Include user_id for sending notifications
            $students = StudentProfile::with([
                'feeDiscounts:student_id,fee_type_id,session_year,discount_id',
                'feeDiscounts.discount:id,discount_type,discount_value',
            ])
                ->active()
                ->where('current_class_id', $class->id)
                ->get(['id', 'user_id']);

            if ($students->isEmpty()) {
                continue;
            }

            $existingSet = StudentFeeInvoice::where('month', $month)
                ->where('year', $year)
                ->whereIn('student_id', $students->pluck('id'))
                ->get(['student_id', 'fee_type_id'])
                ->mapWithKeys(fn ($inv) => ["{$inv->student_id}-{$inv->fee_type_id}" => true])
                ->all();

            $toInsert = [];

            // Track per-student invoice count and amount for notifications
            $notificationData = [];

            foreach ($students as $student) {
                foreach ($structures as $structure) {
                    $key = "{$student->id}-{$structure->fee_type_id}";

                    if (isset($existingSet[$key])) {
                        $skipped++;

                        continue;
                    }

                    $discountAmount = $this->resolveDiscount($student, $structure->fee_type_id, (float) $structure->amount, $year);
                    $netAmount = max(0, $structure->amount - $discountAmount);

                    $toInsert[] = [
                        'student_id' => $student->id,
                        'fee_type_id' => $structure->fee_type_id,
                        'month' => $month,
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
            }

            foreach (array_chunk($toInsert, 500) as $chunk) {
                StudentFeeInvoice::insert($chunk);
            }

            // Notify each student about their new invoices
            if ($notificationData) {
                User::whereIn('id', array_keys($notificationData))
                    ->get(['id'])
                    ->each(function (User $user) use ($notificationData, $month, $year) {
                        $data = $notificationData[$user->id];
                        $user->notify(new FeeInvoiceGeneratedNotification(
                            count: $data['count'],
                            totalAmount: $data['total'],
                            month: $month,
                            year: $year,
                        ));
                    });
            }

            unset($students, $structures, $toInsert, $notificationData, $existingSet);
        }

        $this->info("Done! Generated: {$generated} | Skipped (already exists): {$skipped}");

        return self::SUCCESS;
    }

    private function resolveDiscount(StudentProfile $student, int $feeTypeId, float $originalAmount, int $year): float
    {
        $studentDiscount = $student->feeDiscounts
            ->where('fee_type_id', $feeTypeId)
            ->where('session_year', $year)
            ->first();

        if (! $studentDiscount?->discount) {
            return 0;
        }

        $discount = $studentDiscount->discount;

        return match ($discount->discount_type->value) {
            'percent' => round($originalAmount * $discount->discount_value / 100, 2),
            'fixed' => min($discount->discount_value, $originalAmount),
            default => 0,
        };
    }
}
