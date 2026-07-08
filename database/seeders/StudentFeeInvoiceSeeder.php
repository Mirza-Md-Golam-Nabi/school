<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\FeeStructure;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StudentFeeInvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Monthly (Tuition Fee) invoices are generated from January through the month
     * before the current month. One-time (Exam Fee) invoices are only generated
     * once a matching exam's start_date falls within that same period.
     */
    public function run(): void
    {
        $year = (int) now()->year;
        $currentMonth = (int) now()->month;
        $periodStart = Carbon::create($year, 1, 1)->startOfDay();
        $periodEnd = Carbon::create($year, $currentMonth, 1)->subDay()->endOfDay();

        $classes = Classes::active()->orderBy('order')->get();

        foreach ($classes as $class) {
            $this->seedClassInvoices($class, $year, $currentMonth, $periodStart, $periodEnd);
        }
    }

    private function seedClassInvoices(Classes $class, int $year, int $currentMonth, Carbon $periodStart, Carbon $periodEnd): void
    {
        $students = StudentProfile::with(['feeDiscounts.discount'])
            ->active()
            ->where('current_class_id', $class->id)
            ->get(['id', 'current_class_id']);

        if ($students->isEmpty()) {
            return;
        }

        $structures = FeeStructure::where('class_id', $class->id)
            ->where('session_year', $year)
            ->where('is_active', true)
            ->with('feeType')
            ->get();

        if ($structures->isEmpty()) {
            return;
        }

        $existingKeys = StudentFeeInvoice::whereIn('student_id', $students->pluck('id'))
            ->where('year', $year)
            ->get(['student_id', 'fee_type_id', 'month'])
            ->map(fn (StudentFeeInvoice $invoice) => $this->invoiceKey($invoice->student_id, $invoice->fee_type_id, $invoice->month))
            ->flip();

        $now = now();
        $rows = [];

        foreach ($structures as $structure) {
            $feeType = $structure->feeType;

            if (! $feeType) {
                continue;
            }

            if ($feeType->is_monthly) {
                for ($month = 1; $month < $currentMonth; $month++) {
                    $this->addInvoiceRows($rows, $students, $structure, $month, $year, $existingKeys, $now);
                }

                continue;
            }

            $examTypeName = Str::after($feeType->name, 'Exam Fee - ');

            if ($examTypeName === $feeType->name) {
                continue;
            }

            $examOccurred = Exam::where('class_id', $class->id)
                ->where('session_year', $year)
                ->whereHas('examType', fn ($query) => $query->where('name', $examTypeName))
                ->whereBetween('start_date', [$periodStart, $periodEnd])
                ->exists();

            if (! $examOccurred) {
                continue;
            }

            $this->addInvoiceRows($rows, $students, $structure, null, $year, $existingKeys, $now);
        }

        collect($rows)->chunk(500)->each(fn ($chunk) => StudentFeeInvoice::insert($chunk->all()));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  EloquentCollection<int, StudentProfile>  $students
     * @param  Collection<string, bool>  $existingKeys
     */
    private function addInvoiceRows(
        array &$rows,
        EloquentCollection $students,
        FeeStructure $structure,
        ?int $month,
        int $year,
        Collection $existingKeys,
        Carbon $now
    ): void {
        foreach ($students as $student) {
            $key = $this->invoiceKey($student->id, $structure->fee_type_id, $month);

            if (isset($existingKeys[$key])) {
                continue;
            }

            $discountAmount = $this->resolveDiscount($student, $structure->fee_type_id, (float) $structure->amount, $year);
            $netAmount = max(0, $structure->amount - $discountAmount);

            $rows[] = [
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
        }
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

    private function invoiceKey(int $studentId, int $feeTypeId, ?int $month): string
    {
        return $studentId.'-'.$feeTypeId.'-'.($month ?? 'null');
    }
}
