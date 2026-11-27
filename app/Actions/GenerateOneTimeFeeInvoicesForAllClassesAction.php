<?php

namespace App\Actions;

use App\Actions\Sms\SendFeeInvoiceSmsAction;
use App\Models\FeeStructure;
use App\Models\FeeType;
use InvalidArgumentException;

class GenerateOneTimeFeeInvoicesForAllClassesAction
{
    public function __construct(
        private readonly GenerateOneTimeFeeInvoicesAction $generateOneTimeFeeInvoices,
        private readonly SendFeeInvoiceSmsAction $sendFeeInvoiceSms,
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
            ->with('class')
            ->get()
            ->each(fn (FeeStructure $structure) => $structure->setRelation('feeType', $feeType));

        $generated = 0;
        $skipped = 0;
        $newInvoices = [];

        foreach ($structures as $structure) {
            $result = $this->generateOneTimeFeeInvoices->generateWithoutSms($structure);

            $generated += $result['generated'];
            $skipped += $result['skipped'];
            array_push($newInvoices, ...$result['invoices']);
        }

        // One batch for every class, so the balance check covers the whole run.
        $this->sendFeeInvoiceSms->handle($newInvoices);

        return ['generated' => $generated, 'skipped' => $skipped];
    }
}
