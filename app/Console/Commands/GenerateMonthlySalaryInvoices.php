<?php

namespace App\Console\Commands;

use App\Actions\GenerateMonthlySalaryInvoicesAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('salary:generate-monthly {--month= : Month number (1-12), defaults to current month} {--year= : Year, defaults to current year}')]
#[Description('Generate monthly salary invoices for active teachers/staff based on their salary structures')]
class GenerateMonthlySalaryInvoices extends Command
{
    public function handle(GenerateMonthlySalaryInvoicesAction $action): int
    {
        $month = (int) ($this->option('month') ?? now()->month);
        $year = (int) ($this->option('year') ?? now()->year);

        $this->info("Generating salary invoices for {$month}/{$year}...");

        $result = $action->handle($month, $year);

        $this->info("Done! Generated: {$result['generated']} | Skipped (already exists): {$result['skipped']} | No structure: {$result['no_structure']}");

        return self::SUCCESS;
    }
}
