<?php

namespace App\Console\Commands;

use App\Actions\GenerateMonthlyFeeInvoicesAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fees:generate-monthly {--month= : Month number (1-12), defaults to current month} {--year= : Year, defaults to current year}')]
#[Description('Generate monthly fee invoices for all active students based on their class fee structures')]
class GenerateMonthlyFeeInvoices extends Command
{
    public function handle(GenerateMonthlyFeeInvoicesAction $action): int
    {
        $month = (int) ($this->option('month') ?? now()->month);
        $year = (int) ($this->option('year') ?? now()->year);

        $this->info("Generating fee invoices for {$month}/{$year}...");

        $result = $action->handle($month, $year);

        $this->info("Done! Generated: {$result['generated']} | Skipped (already exists): {$result['skipped']}");

        return self::SUCCESS;
    }
}
