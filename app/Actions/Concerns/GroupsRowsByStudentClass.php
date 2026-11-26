<?php

namespace App\Actions\Concerns;

use App\Models\StudentProfile;
use Closure;
use Illuminate\Database\Eloquent\Builder;

trait GroupsRowsByStudentClass
{
    /**
     * Limit a query on a student-owned model (one with a `student` relation
     * via `student_id`) to students currently in the given classes, and add a
     * `class_id` column holding each row's student's current class — so the
     * caller can `groupBy('class_id', …)` and get every class in one query
     * instead of one query per class.
     *
     * Call it after the caller's own select/selectRaw: on an empty select list
     * addSelect() would add `table.*`, which breaks GROUP BY.
     *
     * @param  array<int, int>  $classIds
     * @param  (Closure(Builder): mixed)|null  $constrainStudents
     */
    private function forStudentsInClasses(Builder $query, array $classIds, ?Closure $constrainStudents = null): Builder
    {
        return $query
            ->addSelect([
                'class_id' => StudentProfile::query()
                    ->select('current_class_id')
                    ->whereColumn(
                        (new StudentProfile)->getQualifiedKeyName(),
                        $query->getModel()->qualifyColumn('student_id'),
                    )
                    ->limit(1),
            ])
            ->whereHas('student', function (Builder $students) use ($classIds, $constrainStudents): void {
                $students->whereIn('current_class_id', $classIds);

                if ($constrainStudents) {
                    $constrainStudents($students);
                }
            });
    }
}
