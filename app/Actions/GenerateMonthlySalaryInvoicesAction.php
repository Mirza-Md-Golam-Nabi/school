<?php

namespace App\Actions;

use App\Actions\Concerns\GeneratesSalaryInvoiceNumber;
use App\Enums\InvoiceStatus;
use App\Enums\SalaryComponentType;
use App\Models\SalaryInvoice;
use App\Models\SalaryStructure;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use App\Notifications\SalaryInvoiceGeneratedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateMonthlySalaryInvoicesAction
{
    use GeneratesSalaryInvoiceNumber;

    /**
     * Generate monthly salary invoices for active teachers/staff based on their
     * currently effective salary structure. Existing invoices for the same
     * profile/month/year are skipped, as are profiles without an effective
     * structure or who joined after the target month.
     *
     * @return array{generated: int, skipped: int, no_structure: int}
     */
    public function handle(int $month, int $year, ?int $createdBy = null): array
    {
        $referenceDate = now()->setDate($year, $month, 1)->endOfMonth()->toDateString();

        $generated = 0;
        $skipped = 0;
        $noStructure = 0;

        foreach ([TeacherProfile::class, StaffProfile::class] as $profileClass) {
            $profiles = $profileClass::query()
                ->active()
                ->where(function ($query) use ($referenceDate) {
                    $query->whereNull('joining_date')->orWhere('joining_date', '<=', $referenceDate);
                })
                ->get(['id', 'user_id']);

            foreach ($profiles as $profile) {
                $alreadyExists = SalaryInvoice::where('profileable_type', $profileClass)
                    ->where('profileable_id', $profile->id)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->exists();

                if ($alreadyExists) {
                    $skipped++;

                    continue;
                }

                $structure = SalaryStructure::where('profileable_type', $profileClass)
                    ->where('profileable_id', $profile->id)
                    ->effectiveOn($referenceDate)
                    ->with('components.component')
                    ->latest('effective_from')
                    ->first();

                if (! $structure) {
                    $noStructure++;

                    continue;
                }

                $invoice = DB::transaction(fn () => $this->createInvoiceFromStructure($structure, $profileClass, $profile->id, $month, $year, $createdBy));

                $generated++;

                if ($profile->user_id) {
                    $profile->user?->notify(new SalaryInvoiceGeneratedNotification($invoice));
                }
            }
        }

        $this->logGeneration($month, $year, $generated, $skipped, $noStructure);

        return ['generated' => $generated, 'skipped' => $skipped, 'no_structure' => $noStructure];
    }

    private function logGeneration(int $month, int $year, int $generated, int $skipped, int $noStructure): void
    {
        $periodLabel = Carbon::create()->month($month)->format('F').' '.$year;

        activity('salary_invoice_generation')
            ->event('generated')
            ->withProperties([
                'attributes' => [
                    'month' => $month,
                    'year' => $year,
                    'generated_count' => $generated,
                    'skipped_count' => $skipped,
                    'no_structure_count' => $noStructure,
                ],
            ])
            ->log("Generated {$generated} monthly salary invoice(s) for {$periodLabel} — {$skipped} skipped (already invoiced), {$noStructure} skipped (no salary structure).");
    }

    protected function createInvoiceFromStructure(SalaryStructure $structure, string $profileClass, int $profileId, int $month, int $year, ?int $createdBy): SalaryInvoice
    {
        if ($structure->use_components) {
            $grossAmount = (float) $structure->components->where('component.type', SalaryComponentType::Allowance)->sum('amount');
            $deductionAmount = (float) $structure->components->where('component.type', SalaryComponentType::Deduction)->sum('amount');
        } else {
            $grossAmount = (float) $structure->flat_amount;
            $deductionAmount = 0.0;
        }

        $netAmount = max(0, $grossAmount - $deductionAmount);

        $invoice = SalaryInvoice::create([
            'invoice_no' => $this->nextInvoiceNo($month, $year),
            'profileable_type' => $profileClass,
            'profileable_id' => $profileId,
            'salary_structure_id' => $structure->id,
            'month' => $month,
            'year' => $year,
            'gross_amount' => $grossAmount,
            'deduction_amount' => $deductionAmount,
            'net_amount' => $netAmount,
            'status' => InvoiceStatus::Unpaid,
            'is_manual' => false,
            'created_by' => $createdBy,
        ]);

        if ($structure->use_components) {
            foreach ($structure->components as $structureComponent) {
                $invoice->components()->create([
                    'salary_component_id' => $structureComponent->salary_component_id,
                    'name' => $structureComponent->component->name,
                    'type' => $structureComponent->component->type,
                    'amount' => $structureComponent->amount,
                ]);
            }
        }

        return $invoice;
    }
}
