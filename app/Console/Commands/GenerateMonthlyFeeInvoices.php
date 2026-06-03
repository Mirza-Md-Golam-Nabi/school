<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Classes;
use App\Models\FeeStructure;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
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

        $classes = Classes::where('is_active', true)->get(['id']);

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

            $students = StudentProfile::with([
                'feeDiscounts:student_id,fee_type_id,session_year,discount_id',
                'feeDiscounts.discount:id,discount_type,discount_value',
            ])
                ->active()
                ->where('current_class_id', $class->id)
                ->get(['id']);

            if ($students->isEmpty()) {
                continue;
            }

            // Only check existing invoices for this class's students
            $existingSet = StudentFeeInvoice::where('month', $month)
                ->where('year', $year)
                ->whereIn('student_id', $students->pluck('id'))
                ->get(['student_id', 'fee_type_id'])
                ->mapWithKeys(fn ($inv) => ["{$inv->student_id}-{$inv->fee_type_id}" => true])
                ->all();

            $toInsert = [];

            foreach ($students as $student) {
                foreach ($structures as $structure) {
                    $key = "{$student->id}-{$structure->fee_type_id}";

                    if (isset($existingSet[$key])) {
                        $skipped++;

                        continue;
                    }

                    $discountAmount = $this->resolveDiscount($student, $structure->fee_type_id, (float) $structure->amount, $year);

                    $toInsert[] = [
                        'student_id' => $student->id,
                        'fee_type_id' => $structure->fee_type_id,
                        'month' => $month,
                        'year' => $year,
                        'original_amount' => $structure->amount,
                        'discount_amount' => $discountAmount,
                        'fine_amount' => 0,
                        'waiver_amount' => 0,
                        'net_amount' => max(0, $structure->amount - $discountAmount),
                        'status' => InvoiceStatus::Unpaid->value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $generated++;
                }
            }

            foreach (array_chunk($toInsert, 500) as $chunk) {
                StudentFeeInvoice::insert($chunk);
            }

            unset($students, $structures, $toInsert);
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
