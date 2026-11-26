<?php

namespace App\Actions;

use App\Actions\Concerns\GroupsRowsByStudentClass;
use App\Enums\StudentStatus;
use App\Models\Classes;
use App\Models\FeeDiscount;
use App\Models\StudentFeeDiscount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AttachClassFeeDiscountStatsAction
{
    use GroupsRowsByStudentClass;

    /**
     * Attach discountStats to each class card: one entry per discount with its
     * name, colour and how many active students of the class hold it. Uses one
     * grouped query for all classes plus one query for the discount names.
     *
     * @param  Collection<int, Classes>  $classes
     * @return Collection<int, Classes>
     */
    public function handle(Collection $classes): Collection
    {
        $rows = $this->forStudentsInClasses(
            StudentFeeDiscount::query()->selectRaw('discount_id, COUNT(DISTINCT student_id) as student_count'),
            $classes->modelKeys(),
            fn (Builder $students) => $students->where('status', StudentStatus::Active),
        )
            ->groupBy('class_id', 'discount_id')
            ->get();

        $discounts = FeeDiscount::query()
            ->whereIn('id', $rows->pluck('discount_id')->unique())
            ->get()
            ->keyBy('id');

        $rowsByClass = $rows->groupBy('class_id');

        return $classes->each(function (Classes $class) use ($rowsByClass, $discounts): void {
            $class->discountStats = $rowsByClass->get($class->id, collect())
                ->filter(fn (StudentFeeDiscount $row): bool => $discounts->has($row->discount_id))
                ->map(fn (StudentFeeDiscount $row): object => (object) [
                    'discount_id' => $row->discount_id,
                    'name' => $discounts[$row->discount_id]->name,
                    'student_count' => (int) $row->student_count,
                    'color' => $discounts[$row->discount_id]->discount_type->getColor(),
                ])
                ->sortBy([['name', 'desc'], ['discount_id', 'asc']])
                ->values()
                ->toBase();
        });
    }
}
