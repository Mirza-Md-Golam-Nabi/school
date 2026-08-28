<?php

namespace Database\Seeders;

use App\Actions\GenerateMonthlySalaryInvoicesAction;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Seeder;

class SalaryInvoiceSeeder extends Seeder
{
    private const MONTHS_BACK = 5;

    /**
     * Run the database seeds.
     *
     * Generates salary invoices for the current month and the 4 months before
     * it (5 months total) for every active teacher/staff who has an effective
     * salary structure — reuses the same action the admin panel's "Generate
     * Monthly Invoices" button calls, so behavior stays identical.
     */
    public function run(): void
    {
        $createdBy = User::where('user_type', UserType::Admin)->value('id')
            ?? User::query()->value('id');

        $action = app(GenerateMonthlySalaryInvoicesAction::class);

        for ($i = self::MONTHS_BACK - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);

            $action->handle($date->month, $date->year, $createdBy);
        }
    }
}
