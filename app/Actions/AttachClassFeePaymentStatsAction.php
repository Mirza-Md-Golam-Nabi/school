<?php

namespace App\Actions;

use App\Actions\Concerns\GroupsRowsByStudentClass;
use App\Models\Classes;
use App\Models\FeePayment;
use Illuminate\Database\Eloquent\Collection;

class AttachClassFeePaymentStatsAction
{
    use GroupsRowsByStudentClass;

    /**
     * Attach the payment summary shown on each class card — paymentStats
     * (keyed by raw payment method), total_payments and total_collected —
     * using one grouped query for all classes.
     *
     * @param  Collection<int, Classes>  $classes
     * @return Collection<int, Classes>
     */
    public function handle(Collection $classes): Collection
    {
        $statsByClass = $this->forStudentsInClasses(
            FeePayment::query()->selectRaw('payment_method, COUNT(*) as count, COALESCE(SUM(amount_paid), 0) as total_amount'),
            $classes->modelKeys(),
        )
            ->groupBy('class_id', 'payment_method')
            ->get()
            ->groupBy('class_id');

        return $classes->each(function (Classes $class) use ($statsByClass): void {
            $stats = $statsByClass->get($class->id, collect())
                ->keyBy(fn (FeePayment $row) => $row->getRawOriginal('payment_method'));

            $class->paymentStats = $stats;
            $class->total_payments = $stats->sum('count');
            $class->total_collected = $stats->sum('total_amount');
        });
    }
}
