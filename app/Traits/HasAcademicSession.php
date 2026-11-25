<?php

namespace App\Traits;

use App\Enums\ClassLevel;
use App\Models\Classes;
use App\Support\AcademicSession;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * For models with a `session_year` and a class column: filters rows to the
 * session that is current for each row's own class, so college rows (July
 * start) and school rows (January start) both show correctly side by side.
 */
trait HasAcademicSession
{
    /**
     * The column that holds the row's class id.
     */
    public function academicSessionClassColumn(): string
    {
        return 'class_id';
    }

    #[Scope]
    protected function inCurrentSession(Builder $query): void
    {
        $sessionColumn = $query->qualifyColumn('session_year');
        $schoolYear = AcademicSession::currentYearFor(null);
        $collegeYear = AcademicSession::currentYearFor(ClassLevel::College);

        if ($schoolYear === $collegeYear) {
            $query->where($sessionColumn, $schoolYear);

            return;
        }

        $classColumn = $query->qualifyColumn($this->academicSessionClassColumn());
        $collegeClassIds = Classes::withTrashed()
            ->select('id')
            ->where('level', ClassLevel::College);

        $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query
                ->whereIn($classColumn, $collegeClassIds)
                ->where($sessionColumn, $collegeYear))
            ->orWhere(fn (Builder $query) => $query
                ->whereNotIn($classColumn, $collegeClassIds)
                ->where($sessionColumn, $schoolYear)));
    }
}
