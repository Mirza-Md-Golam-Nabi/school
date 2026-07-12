<?php

namespace App\Actions\Concerns;

use App\Models\StudentProfile;

trait ResolvesFeeDiscount
{
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
