<?php

namespace App\Filament\Widgets;

use App\Enums\LeaveApplicationStatus;
use App\Filament\Resources\Exams\ExamResource;
use App\Filament\Resources\LeaveApplications\LeaveApplicationResource;
use App\Filament\Resources\StudentFeeInvoices\StudentFeeInvoiceResource;
use App\Models\Exam;
use App\Models\FeePayment;
use App\Models\LeaveApplication;
use App\Models\StudentFeeInvoice;
use Filament\Widgets\Widget;

class PendingAlertsWidget extends Widget
{
    protected string $view = 'filament.widgets.pending-alerts';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = [
        'default' => 'full',
    ];

    protected static bool $isLazy = false;

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $unpublishedExams = Exam::query()
            ->where('session_year', now()->year)
            ->where('is_published', false)
            ->whereDate('end_date', '<=', today())
            ->count();

        $pendingLeaves = LeaveApplication::query()
            ->where('status', LeaveApplicationStatus::Pending)
            ->count();

        $overdueInvoices = StudentFeeInvoice::payable()->count();
        $netDue = (float) StudentFeeInvoice::payable()->sum('net_amount');
        $paid = (float) FeePayment::query()
            ->whereIn('invoice_id', StudentFeeInvoice::payable()->select('id'))
            ->sum('amount_paid');
        $totalDue = max(0, $netDue - $paid);

        $alerts = [];

        if ($unpublishedExams > 0) {
            $alerts[] = [
                'label' => __('Exam Finished, Result Not Published'),
                'count' => $this->formatCount($unpublishedExams),
                'icon' => 'heroicon-o-clipboard-document-check',
                'color' => 'warning',
                'url' => ExamResource::getUrl('index'),
            ];
        }

        if ($pendingLeaves > 0) {
            $alerts[] = [
                'label' => __('Leave Application Pending'),
                'count' => $this->formatCount($pendingLeaves),
                'icon' => 'heroicon-o-envelope-open',
                'color' => 'info',
                'url' => LeaveApplicationResource::getUrl('index'),
            ];
        }

        if ($overdueInvoices > 0) {
            $alerts[] = [
                'label' => __('Due Fee (:count invoices)', ['count' => $this->localizeNumber($overdueInvoices)]),
                'count' => '৳ '.number_format($totalDue),
                'icon' => 'heroicon-o-banknotes',
                'color' => 'danger',
                'url' => StudentFeeInvoiceResource::getUrl('index'),
            ];
        }

        return ['alerts' => $alerts];
    }

    protected function formatCount(int $count): string
    {
        return app()->isLocale('bn')
            ? $this->localizeNumber($count).' টি'
            : (string) $count;
    }

    protected function localizeNumber(int $number): string
    {
        return app()->isLocale('bn')
            ? convertEnglishToBengali((string) $number)
            : (string) $number;
    }
}
