<?php

namespace App\Filament\Student\Widgets;

use App\Enums\InvoiceStatus;
use App\Filament\Student\Resources\FeeInvoices\FeeInvoiceResource;
use App\Models\StudentFeeInvoice;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class StudentFeeDueOverview extends Widget
{
    protected string $view = 'filament.student.widgets.fee-due-overview';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = [
        'default' => 1,
    ];

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        $profile = Auth::user()->studentProfile;
        $url = FeeInvoiceResource::getUrl('index', panel: 'student');

        if (! $profile) {
            return [
                'totalDue' => 0,
                'totalDiscount' => 0,
                'pendingCount' => 0,
                'url' => $url,
            ];
        }

        $pendingInvoices = StudentFeeInvoice::where('student_id', $profile->id)
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->withSum('payments as paid_amount', 'amount_paid')
            ->get();

        $totalDue = $pendingInvoices->sum(
            fn (StudentFeeInvoice $invoice) => $invoice->net_amount - ($invoice->paid_amount ?? 0)
        );

        $totalDiscount = StudentFeeInvoice::where('student_id', $profile->id)->sum('discount_amount');

        return [
            'totalDue' => $totalDue,
            'totalDiscount' => $totalDiscount,
            'pendingCount' => $pendingInvoices->count(),
            'url' => $url,
        ];
    }
}
