<?php

namespace App\Traits;

use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Builder;

/**
 * For models that hold a student_id (admit cards, marksheets, merit
 * rankings ...): sorts their rows by the student's roll number in the
 * database, then by id so rows with the same roll keep a stable order.
 */
trait OrdersByStudentRoll
{
    public function scopeOrderedByStudentRoll(Builder $query): Builder
    {
        return $query
            ->orderBy(
                StudentProfile::withTrashed()
                    ->select('roll_no')
                    ->whereColumn('student_profiles.id', $this->qualifyColumn('student_id'))
                    ->limit(1)
            )
            ->orderBy($this->qualifyColumn('id'));
    }
}
