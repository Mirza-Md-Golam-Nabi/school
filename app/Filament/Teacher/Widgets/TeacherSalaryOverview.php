<?php

namespace App\Filament\Teacher\Widgets;

use App\Enums\InvoiceStatus;
use App\Filament\Teacher\Pages\MySalary;
use App\Models\SalaryInvoice;
use App\Models\TeacherProfile;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class TeacherSalaryOverview extends Widget
{
    protected string $view = 'filament.teacher.widgets.salary-overview';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        $profile = Auth::user()?->teacherProfile;
        $url = MySalary::getUrl(panel: 'teacher');

        if (! $profile) {
            return ['totalDue' => 0.0, 'dueCount' => 0, 'url' => $url];
        }

        $dueInvoices = SalaryInvoice::query()
            ->where('profileable_type', TeacherProfile::class)
            ->where('profileable_id', $profile->id)
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->with('payments')
            ->get();

        return [
            'totalDue' => (float) $dueInvoices->sum(fn (SalaryInvoice $invoice) => $invoice->due_amount),
            'dueCount' => $dueInvoices->count(),
            'url' => $url,
        ];
    }
}
