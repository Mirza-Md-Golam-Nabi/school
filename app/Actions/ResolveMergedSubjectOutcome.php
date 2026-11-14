<?php

namespace App\Actions;

use App\Enums\MergedAbsentRule;
use App\Enums\MergedComponentPassRule;
use App\Enums\MergedPassRule;
use App\Enums\ResultRounding;
use App\Models\ExamSubjectConfig;
use App\Models\ExamSubjectMerge;
use App\Models\GradeScale;
use App\Models\StudentResult;
use Illuminate\Support\Collection;

class ResolveMergedSubjectOutcome
{
    /**
     * Full marks a merged subject's marks are converted to.
     */
    public const FULL_MARKS = 100.0;

    public function __construct(private readonly ResolveSubjectOutcome $resolveSubjectOutcome) {}

    /**
     * A student's marks, grade and pass/fail in a merged subject (e.g. Bangla
     * 1st + 2nd paper) — the single decision the merit ranking and the marks
     * detail / marksheet / tabulation sheet all use.
     *
     * Marks = (1st + 2nd paper marks) ÷ (1st + 2nd full marks) × 100, rounded
     * by the merge's rounding; the grade comes from that rounded number. A
     * paper's marks are its `effective_marks`, so a result built by a result
     * rule is merged like any other. A paper with no result, or absent, is
     * absent: with the "fail" absent rule the subject fails, with "count as
     * 0" it adds 0 marks (and fails any pass mark it is checked against).
     * A paper absent in only its theory or practical exam follows the same
     * absent rule, for that part's marks.
     *
     * Pass — "each paper": every paper passes on its own, exactly as an
     * unmerged subject would. "Combined": the merged marks reach the papers'
     * combined pass percentage, a paper's result-rule component that must be
     * passed on its own is passed, and the MCQ / written / practical pass
     * marks are met — added together across the papers that check them, or
     * per paper, by the component rule.
     *
     * @param  Collection<int, GradeScale>  $gradeScales
     * @return array{marks: float, full_marks: float, grade: ?GradeScale, is_fail: bool, is_absent: bool}
     */
    public function execute(
        ExamSubjectMerge $merge,
        ?StudentResult $firstResult,
        ?StudentResult $secondResult,
        ?ExamSubjectConfig $firstConfig,
        ?ExamSubjectConfig $secondConfig,
        Collection $gradeScales,
    ): array {
        $papers = [
            $this->paper($firstResult, $firstConfig, $gradeScales),
            $this->paper($secondResult, $secondConfig, $gradeScales),
        ];

        $papersFullMarks = array_sum(array_column($papers, 'full_marks'));
        $percentage = $papersFullMarks > 0 ? array_sum(array_column($papers, 'marks')) / $papersFullMarks * 100 : 0.0;

        $rounding = $merge->rounding ?? ResultRounding::Decimal;
        $marks = $rounding->apply($percentage / 100 * self::FULL_MARKS);

        $grade = GradeScale::fromMarks($marks / self::FULL_MARKS * 100, $gradeScales);

        $anyAbsent = collect($papers)->contains(fn (array $paper): bool => $paper['is_absent'] || $paper['is_partly_absent']);
        $allAbsent = collect($papers)->every(fn (array $paper): bool => $paper['is_absent']);

        $missedPassMark = match (true) {
            $anyAbsent && ($merge->absent_rule ?? MergedAbsentRule::Fail) === MergedAbsentRule::Fail => true,
            ($merge->pass_rule ?? MergedPassRule::Combined) === MergedPassRule::EachPaper => collect($papers)->contains('passes_alone', false),
            default => ! $this->passesCombined($merge, $papers, $marks / self::FULL_MARKS * 100),
        };

        $isFail = $grade === null || $grade->grade_point <= 0.0 || $missedPassMark;

        if ($isFail && $grade !== null && $grade->grade_point > 0.0) {
            $grade = GradeScale::failing($gradeScales);
        }

        return [
            'marks' => $marks,
            'full_marks' => self::FULL_MARKS,
            'grade' => $grade,
            'is_fail' => $isFail,
            'is_absent' => $allAbsent,
        ];
    }

    /**
     * @param  Collection<int, GradeScale>  $gradeScales
     * @return array{result: ?StudentResult, config: ?ExamSubjectConfig, is_absent: bool, is_partly_absent: bool, is_weighted: bool, marks: float, full_marks: float, pass_percentage: float, passes_alone: bool}
     */
    private function paper(?StudentResult $result, ?ExamSubjectConfig $config, Collection $gradeScales): array
    {
        $ownTotalMarks = (float) ($config?->total_marks ?: 100);
        $isWeighted = $result?->hasWeightedResult() ?? false;
        $isAbsent = $result === null || ($result->is_absent && ! $isWeighted);

        return [
            'result' => $result,
            'config' => $config,
            'is_absent' => $isAbsent,
            'is_partly_absent' => ! $isAbsent && ! $isWeighted && $result->isPartlyAbsent(),
            'is_weighted' => $isWeighted,
            'marks' => $isAbsent ? 0.0 : (float) $result->effective_marks,
            'full_marks' => $result?->resolveFullMarks($ownTotalMarks) ?? $ownTotalMarks,
            'pass_percentage' => $config?->passPercentage() ?? 0.0,
            'passes_alone' => $result !== null
                && ! $isAbsent
                && ! $this->resolveSubjectOutcome->execute($result, $config, $gradeScales)['is_fail'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $papers
     */
    private function passesCombined(ExamSubjectMerge $merge, array $papers, float $mergedPercentage): bool
    {
        $papersFullMarks = array_sum(array_column($papers, 'full_marks'));
        $requiredPercentage = $papersFullMarks > 0
            ? collect($papers)->sum(fn (array $paper): float => $paper['pass_percentage'] * $paper['full_marks']) / $papersFullMarks
            : 0.0;

        if (round($mergedPercentage, 2) < round($requiredPercentage, 2)) {
            return false;
        }

        foreach ($papers as $paper) {
            if ($paper['is_weighted'] && $paper['result']->failsARequiredComponent()) {
                return false;
            }
        }

        // MCQ / written / practical marks exist only for an exam's own result.
        $partPapers = collect($papers)->reject(fn (array $paper): bool => $paper['is_weighted'] || $paper['config'] === null);

        if (($merge->component_pass_rule ?? MergedComponentPassRule::Combined) === MergedComponentPassRule::EachPaper) {
            return $partPapers->every(fn (array $paper): bool => $paper['is_absent']
                ? $paper['config']->checkedPartPassMarks() === []
                : $paper['config']->passesPartMarks($paper['result']));
        }

        foreach (['mcq', 'written', 'practical'] as $part) {
            $checking = $partPapers->filter(fn (array $paper): bool => array_key_exists($part, $paper['config']->checkedPartPassMarks()));

            if ($checking->isEmpty()) {
                continue;
            }

            $obtained = $checking->sum(fn (array $paper): float => $paper['is_absent'] ? 0.0 : (float) $paper['result']->{"{$part}_marks"});
            $required = $checking->sum(fn (array $paper): float => $paper['config']->checkedPartPassMarks()[$part]);

            if ($obtained < $required) {
                return false;
            }
        }

        return true;
    }
}
