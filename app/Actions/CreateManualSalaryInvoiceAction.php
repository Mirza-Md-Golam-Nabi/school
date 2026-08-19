<?php

namespace App\Actions;

use App\Actions\Concerns\GeneratesSalaryInvoiceNumber;
use App\Enums\InvoiceStatus;
use App\Models\SalaryInvoice;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateManualSalaryInvoiceAction
{
    use GeneratesSalaryInvoiceNumber;

    /**
     * Manually create a single salary invoice with a flat, admin-entered net amount.
     * Used for cases the automatic monthly generation doesn't cover (mid-month
     * joiners, resigned/inactive staff, corrections, etc). No component breakdown
     * is recorded — this is a flat amount, not structure-derived.
     */
    public function handle(string $profileableType, int $profileableId, int $month, int $year, float $netAmount, ?int $createdBy = null): SalaryInvoice
    {
        $alreadyExists = SalaryInvoice::where('profileable_type', $profileableType)
            ->where('profileable_id', $profileableId)
            ->where('month', $month)
            ->where('year', $year)
            ->exists();

        if ($alreadyExists) {
            throw new \RuntimeException('এই মাসের জন্য ইতিমধ্যে একটা invoice আছে।');
        }

        return DB::transaction(function () use ($profileableType, $profileableId, $month, $year, $netAmount, $createdBy) {
            $invoice = SalaryInvoice::create([
                'invoice_no' => $this->nextInvoiceNo($month, $year),
                'profileable_type' => $profileableType,
                'profileable_id' => $profileableId,
                'salary_structure_id' => null,
                'month' => $month,
                'year' => $year,
                'gross_amount' => $netAmount,
                'deduction_amount' => 0,
                'net_amount' => $netAmount,
                'status' => InvoiceStatus::Unpaid,
                'is_manual' => true,
                'created_by' => $createdBy,
            ]);

            $this->logManualCreation($invoice);

            return $invoice;
        });
    }

    private function logManualCreation(SalaryInvoice $invoice): void
    {
        $personLabel = $this->resolveProfileableLabel($invoice);
        $periodLabel = Carbon::create()->month($invoice->month)->format('F').' '.$invoice->year;
        $amountLabel = number_format((float) $invoice->net_amount, 2);

        activity('salary_invoice_generation')
            ->performedOn($invoice)
            ->event('created')
            ->withProperties([
                'attributes' => [
                    'invoice_no' => $invoice->invoice_no,
                    'profileable_id' => $invoice->profileable_id,
                    'profileable_id_label' => $personLabel,
                    'month' => $invoice->month,
                    'year' => $invoice->year,
                    'net_amount' => $invoice->net_amount,
                ],
            ])
            ->log("Manually created salary invoice {$invoice->invoice_no} for \"{$personLabel}\" ({$periodLabel}) — ৳{$amountLabel}.");
    }

    private function resolveProfileableLabel(SalaryInvoice $invoice): string
    {
        $profileable = $invoice->profileable;

        if ($profileable instanceof TeacherProfile || $profileable instanceof StaffProfile) {
            return $profileable->user?->name ?? class_basename($profileable)." #{$invoice->profileable_id}";
        }

        return "#{$invoice->profileable_id}";
    }
}
