<?php

namespace App\Actions;

use App\Models\FeeStructure;
use App\Models\FeeType;
use InvalidArgumentException;

class GenerateOneTimeFeeInvoicesForAllClassesAction
{
    public function __construct(
        private readonly GenerateOneTimeFeeInvoicesAction $generateOneTimeFeeInvoices,
    ) {}

    /**
     * Generate one-time invoices for the given fee type/year across every active class
     * that has an active fee structure for it. Delegates the actual per-class invoice
     * creation (including discount resolution, duplicate skipping, and notifications)
     * to GenerateOneTimeFeeInvoicesAction so that logic exists in exactly one place.
     *
     * @return array{generated: int, skipped: int}
     */
    public function handle(int $feeTypeId, int $year): array
    {
        $feeType = FeeType::findOrFail($feeTypeId);

        if ($feeType->is_monthly) {
            throw new InvalidArgumentException('Fee type must be a one-time (non-monthly) fee type.');
        }

        $structures = FeeStructure::where('fee_type_id', $feeTypeId)
            ->where('session_year', $year)
            ->where('is_active', true)
            ->whereHas('class', fn ($q) => $q->active())
            ->get();

        $generated = 0;
        $skipped = 0;

        foreach ($structures as $structure) {
            $result = $this->generateOneTimeFeeInvoices->handle($structure);

            $generated += $result['generated'];
            $skipped += $result['skipped'];
        }

        return ['generated' => $generated, 'skipped' => $skipped];
    }
}
