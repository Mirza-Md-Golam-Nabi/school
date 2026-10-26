<?php

namespace App\Actions;

use App\Models\ExamSubjectConfig;
use App\Models\GradeScale;
use App\Models\StudentResult;
use Illuminate\Support\Collection;

class ResolveSubjectOutcome
{
    /**
     * A student's percentage, grade and pass/fail in one subject of one exam
     * — the single decision the merit ranking (GPA, overall fail) and the
     * marks detail / marksheet both use, so they never disagree.
     *
     * A result built by a result rule fails when its percentage grades F or
     * a component that must be passed on its own was not. An exam's own
     * result fails when absent, when it grades F, or when it misses a pass
     * mark (overall or MCQ / written / practical where those are checked).
     * A failed subject always shows the F grade.
     *
     * @param  Collection<int, GradeScale>  $gradeScales
     * @return array{percentage: float, grade: ?GradeScale, is_fail: bool}
     */
    public function execute(StudentResult $result, ?ExamSubjectConfig $config, Collection $gradeScales): array
    {
        if (! $result->hasWeightedResult() && $result->is_absent) {
            return ['percentage' => 0.0, 'grade' => null, 'is_fail' => true];
        }

        $fullMarks = $result->resolveFullMarks((float) ($config?->total_marks ?: 100));
        $percentage = $fullMarks > 0 ? $result->effective_marks / $fullMarks * 100 : 0.0;
        $grade = GradeScale::fromMarks($percentage, $gradeScales);

        $missedPassMark = $result->hasWeightedResult()
            ? $result->failsARequiredComponent()
            : ($config !== null && ! $config->isPassedBy($result));

        $isFail = $grade === null || $grade->grade_point <= 0.0 || $missedPassMark;

        if ($isFail && $grade !== null && $grade->grade_point > 0.0) {
            $grade = GradeScale::failing($gradeScales);
        }

        return ['percentage' => $percentage, 'grade' => $grade, 'is_fail' => $isFail];
    }
}
