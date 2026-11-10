<?php

namespace App\Traits;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;

/**
 * For models that hold a subject_id (results, exam subject configs, class
 * subjects ...): sorts their rows in the school-wide subject order defined by
 * Subject::scopeOrdered() — sort_order first, then the subject id — so every
 * marksheet and result page lists subjects in the same sequence.
 */
trait OrdersBySubject
{
    public function scopeOrderedBySubject(Builder $query): Builder
    {
        $subjectIdColumn = $this->qualifyColumn('subject_id');

        return $query
            ->orderBy(
                Subject::withTrashed()
                    ->select('sort_order')
                    ->whereColumn('subjects.id', $subjectIdColumn)
                    ->limit(1)
            )
            ->orderBy($subjectIdColumn);
    }
}
