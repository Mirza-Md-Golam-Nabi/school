<?php

namespace App\Actions;

use App\Actions\Concerns\GroupsRowsByStudentClass;
use App\Enums\InvoiceStatus;
use App\Models\Classes;
use App\Models\StudentFeeInvoice;
use Illuminate\Database\Eloquent\Collection;

class AttachClassFeeInvoiceStatsAction
{
    use GroupsRowsByStudentClass;

    /**
     * Attach the invoice summary shown on each class card — invoiceStats
     * (keyed by raw status), total_invoices and outstanding_amount — using one
     * grouped query for all classes.
     *
     * @param  Collection<int, Classes>  $classes
     * @return Collection<int, Classes>
     */
    public function handle(Collection $classes): Collection
    {
        $statsByClass = $this->forStudentsInClasses(
            StudentFeeInvoice::query()->selectRaw('status, COUNT(*) as count, COALESCE(SUM(net_amount), 0) as total_amount, COALESCE(SUM(default_amount), 0) as defaulted_amount'),
            $classes->modelKeys(),
        )
            ->groupBy('class_id', 'status')
            ->get()
            ->groupBy('class_id');

        $outstandingStatuses = [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value];

        return $classes->each(function (Classes $class) use ($statsByClass, $outstandingStatuses): void {
            $stats = $statsByClass->get($class->id, collect())
                ->keyBy(fn (StudentFeeInvoice $row) => $row->getRawOriginal('status'));

            $class->invoiceStats = $stats;
            $class->total_invoices = $stats->sum('count');
            $class->outstanding_amount = $stats
                ->filter(fn (StudentFeeInvoice $row): bool => in_array($row->getRawOriginal('status'), $outstandingStatuses, true))
                ->sum('total_amount');
        });
    }
}
