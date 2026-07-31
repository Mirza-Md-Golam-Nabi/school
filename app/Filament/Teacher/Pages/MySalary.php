<?php

namespace App\Filament\Teacher\Pages;

use App\Enums\InvoiceStatus;
use App\Models\SalaryInvoice;
use App\Models\TeacherProfile;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class MySalary extends Page
{
    protected string $view = 'filament.teacher.pages.my-salary';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Salary';

    protected static ?string $navigationLabel = 'My Salary';

    protected static ?int $navigationSort = 5;

    public function getViewData(): array
    {
        $profile = $this->resolveProfile();

        if (! $profile) {
            return ['dueInvoices' => collect(), 'paidInvoices' => collect(), 'totalDue' => 0.0];
        }

        $invoices = SalaryInvoice::query()
            ->where('profileable_type', TeacherProfile::class)
            ->where('profileable_id', $profile->id)
            ->with('payments')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        $dueInvoices = $invoices->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])->values();
        $paidInvoices = $invoices->where('status', InvoiceStatus::Paid)->values();

        return [
            'dueInvoices' => $dueInvoices,
            'paidInvoices' => $paidInvoices,
            'totalDue' => (float) $dueInvoices->sum(fn (SalaryInvoice $invoice) => $invoice->due_amount),
        ];
    }

    private function resolveProfile(): ?TeacherProfile
    {
        return auth()->user()?->teacherProfile;
    }
}
