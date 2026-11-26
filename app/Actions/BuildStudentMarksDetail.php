<?php

namespace App\Actions;

use App\Enums\AttendanceStatus;
use App\Enums\OptionalSubjectRole;
use App\Models\Attendance;
use App\Models\ClassGroupSubject;
use App\Models\ExamSubjectConfig;
use App\Models\ExamSubjectMerge;
use App\Models\GradeScale;
use App\Models\StudentAttendanceSummary;
use App\Models\StudentMeritRanking;
use App\Models\StudentOptionalSubject;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class BuildStudentMarksDetail
{
    /** @var array<string, array<string, mixed>> */
    private array $examContexts = [];

    public function __construct(
        private readonly ResolveIsExtraOptionalSubject $resolveIsExtraOptionalSubject,
        private readonly ResolveSubjectOutcome $resolveSubjectOutcome,
        private readonly ResolveMergedSubjectOutcome $resolveMergedSubjectOutcome,
    ) {}

    /**
     * Build the per-subject marks rows (own marks, result-rule components, final, grade, top-scorer)
     * and the overall summary for a student's ranking in one exam. Shared between
     * the student-facing result view and the marksheet PDF generator so both
     * always show identical numbers.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function handle(StudentMeritRanking $ranking): array
    {
        $context = $this->examContext($ranking);
        [
            'topRanking' => $topRanking,
            'sessionYear' => $sessionYear,
            'workingDays' => $workingDays,
            'attendanceSummaries' => $attendanceSummaries,
        ] = $context;

        $gradeScales = GradeScale::cached();

        $placedRows = $this->placedRows($ranking, $context, $gradeScales);
        $obtained = $this->obtainedMarks($placedRows);

        // Subject order from the Subjects page, except the student's main
        // optional subject goes second-to-last and the additional one last.
        $rows = $placedRows
            ->map(fn (array $row): array => collect($row)->except(['position_group', 'obtained_marks', 'full_marks'])->all())
            ->values();

        $additionalGradePoint = (float) ($placedRows->firstWhere('position_group', 2)['grade_point'] ?? 0);

        $overallGrade = GradeScale::fromGpa((float) $ranking->gpa, $gradeScales);

        // "1st position" means class_rank = 1 (the top exam performer), never roll_no = 1.
        $topRankGrade = $topRanking ? GradeScale::fromGpa((float) $topRanking->gpa, $gradeScales) : null;

        // A total written by the class teacher / admin for this exam replaces
        // the count of daily attendance rows.
        $attendanceSummary = $attendanceSummaries->get($ranking->student_id);

        if ($attendanceSummary) {
            $workingDays = $attendanceSummary->working_days;
            $presentDays = $attendanceSummary->present_days;
        } else {
            $presentDays = Attendance::where('attendable_type', StudentProfile::class)
                ->where('attendable_id', $ranking->student_id)
                ->where('class_id', $ranking->class_id)
                ->countedPresent(StudentProfile::class)
                ->inSession($sessionYear)
                ->count();
        }

        $summary = [
            'total_marks' => $ranking->total_marks,
            'class_rank' => $ranking->class_rank,
            'section_rank' => $ranking->section_rank,
            'gpa' => number_format((float) $ranking->gpa, 2),
            'obtained_marks' => $obtained['obtained_marks'],
            'obtained_full_marks' => $obtained['full_marks'],
            // Every subject's grade point, less the additional subject's
            // first 2 points (all of it when it earned less than 2).
            'total_grade_point' => number_format(
                (float) $rows->sum(fn (array $row): float => (float) ($row['grade_point'] ?? 0))
                    - min($additionalGradePoint, CalculateExamRankings::ADDITIONAL_SUBJECT_GRADE_POINT_DEDUCTION),
                2,
            ),
            'overall_grade_label' => $overallGrade?->letter_grade,
            'overall_grade_color' => $overallGrade?->color,
            'top_rank_total_marks' => $topRanking?->total_marks,
            'top_rank_obtained_marks' => $topRanking ? $this->topRankObtainedMarks($topRanking, $context, $gradeScales) : null,
            'top_rank_gpa' => $topRanking ? number_format((float) $topRanking->gpa, 2) : null,
            'top_rank_grade_label' => $topRankGrade?->letter_grade,
            'working_days' => $workingDays,
            'present_days' => $presentDays,
        ];

        return ['rows' => $rows, 'summary' => $summary];
    }

    /**
     * The student's subject rows in marksheet order, still carrying
     * position_group, obtained_marks and full_marks for the summary.
     *
     * @param  array<string, mixed>  $context
     * @param  Collection<int, GradeScale>  $gradeScales
     * @return Collection<int, array<string, mixed>>
     */
    private function placedRows(StudentMeritRanking $ranking, array $context, Collection $gradeScales): Collection
    {
        $myResults = StudentResult::where('exam_id', $ranking->exam_id)
            ->where('student_id', $ranking->student_id)
            ->with('subject')
            ->orderedBySubject()
            ->get()
            ->keyBy('subject_id');

        $student = $ranking->student;
        $groupId = $student?->current_group_id ? (int) $student->current_group_id : null;

        $studentOptionalSelections = StudentOptionalSubject::where('class_id', $ranking->class_id)
            ->where('student_id', $ranking->student_id)
            ->get();

        // Only the merges whose papers the student's group both takes.
        $mergeBySubject = $this->mergeBySubject(ExamSubjectMerge::applyingToGroup($context['merges'], $context['subjectTypeRecords'], $groupId));

        $rows = collect();
        $emittedMergeIds = [];

        // In subject order; a merged subject takes the place of its first paper found.
        foreach ($myResults as $subjectId => $result) {
            $merge = $mergeBySubject->get((int) $subjectId);

            if (! $merge) {
                $rows->push($this->subjectRow($result, $context, $gradeScales, $studentOptionalSelections, $groupId));

                continue;
            }

            if (in_array($merge->id, $emittedMergeIds, true)) {
                continue;
            }

            $emittedMergeIds[] = $merge->id;
            $rows->push($this->mergedRow($merge, $myResults, $context, $gradeScales, $studentOptionalSelections, $groupId));
        }

        return $rows->sortBy('position_group')->values();
    }

    /**
     * "Obtained Marks": every paper's own marks added up (a merged subject's
     * papers count separately, never its merged marks), less the additional
     * subject's first 40% of its full marks — 40 of 100; all of it when it
     * earned less. Full marks are every paper's full marks added up.
     *
     * @param  Collection<int, array<string, mixed>>  $placedRows
     * @return array{obtained_marks: float, full_marks: float}
     */
    private function obtainedMarks(Collection $placedRows): array
    {
        $obtained = 0.0;

        foreach ($placedRows as $row) {
            $marks = (float) $row['obtained_marks'];

            if ($row['position_group'] === 2) {
                $marks -= min($marks, (float) $row['full_marks'] * CalculateExamRankings::ADDITIONAL_SUBJECT_MARKS_DEDUCTION_PERCENTAGE / 100);
            }

            $obtained += $marks;
        }

        return [
            'obtained_marks' => round($obtained, 2),
            'full_marks' => round((float) $placedRows->sum('full_marks'), 2),
        ];
    }

    /**
     * The 1st position student's obtained marks — the same on every
     * marksheet of the exam + class, so it is cached for a minute.
     *
     * @param  array<string, mixed>  $context
     * @param  Collection<int, GradeScale>  $gradeScales
     */
    private function topRankObtainedMarks(StudentMeritRanking $topRanking, array $context, Collection $gradeScales): float
    {
        return (float) Cache::remember(
            "marksheet:top-rank-obtained-marks:{$topRanking->exam_id}:{$topRanking->class_id}",
            now()->addMinute(),
            fn (): float => $this->obtainedMarks($this->placedRows($topRanking, $context, $gradeScales))['obtained_marks'],
        );
    }

    /**
     * One subject that is not merged with another.
     *
     * @param  array<string, mixed>  $context
     * @param  Collection<int, GradeScale>  $gradeScales
     * @param  Collection<int, StudentOptionalSubject>  $studentOptionalSelections
     * @return array<string, mixed>
     */
    private function subjectRow(StudentResult $result, array $context, Collection $gradeScales, Collection $studentOptionalSelections, ?int $groupId): array
    {
        $subjectId = (int) $result->subject_id;
        $grade = $this->resolveSubjectOutcome->execute($result, $context['subjectConfigs']->get($subjectId), $gradeScales)['grade'];
        $best = $context['subjectBestMap'][$subjectId] ?? null;
        $paper = $this->paperColumns($result);

        return [
            ...$this->subjectPlacement($subjectId, $context['subjectConfigs']->get($subjectId)?->subjectDisplayName($result->subject) ?? $result->subject?->display_name ?? '—', $context, $studentOptionalSelections, $groupId),
            ...collect($paper)->except('paper_label')->all(),
            'grade_label' => $grade?->letter_grade,
            'grade_color' => $grade?->color,
            'grade_point' => $grade?->grade_point,
            'is_top_scorer' => $best !== null
                && ! $paper['is_absent']
                && (float) $result->effective_marks === (float) $best['best_marks'],
            'best_marks' => $best['best_marks'] ?? null,
            'best_students' => $best['best_students'] ?? null,
            'papers' => null,
            'obtained_marks' => $result->effective_marks,
            'full_marks' => $this->paperFullMarks($result, $context),
        ];
    }

    /**
     * A merged subject (e.g. Bangla 1st + 2nd paper): one row with the
     * merged marks and grade, and each paper's own marks under `papers`.
     *
     * @param  Collection<int, StudentResult>  $myResults
     * @param  array<string, mixed>  $context
     * @param  Collection<int, GradeScale>  $gradeScales
     * @param  Collection<int, StudentOptionalSubject>  $studentOptionalSelections
     * @return array<string, mixed>
     */
    private function mergedRow(ExamSubjectMerge $merge, Collection $myResults, array $context, Collection $gradeScales, Collection $studentOptionalSelections, ?int $groupId): array
    {
        [$firstId, $secondId] = $merge->subjectIds();
        $first = $myResults->get($firstId);
        $second = $myResults->get($secondId);

        $outcome = $this->resolveMergedSubjectOutcome->execute(
            $merge,
            $first,
            $second,
            $context['subjectConfigs']->get($firstId),
            $context['subjectConfigs']->get($secondId),
            $gradeScales,
        );
        $best = $context['subjectBestMap']["merge:{$merge->id}"] ?? null;

        $papers = collect([[$first, __('1st')], [$second, __('2nd')]])
            ->map(fn (array $paper): array => $paper[0]
                ? ['paper_label' => $paper[1], ...$this->paperColumns($paper[0])]
                : ['paper_label' => $paper[1], 'is_absent' => true, 'is_theory_absent' => true, 'is_practical_absent' => true, 'mcq_marks' => null, 'written_marks' => null, 'practical_marks' => null, 'total_marks' => null, 'components' => []])
            ->all();

        return [
            ...$this->subjectPlacement($first ? $firstId : $secondId, $merge->display_name, $context, $studentOptionalSelections, $groupId),
            'is_absent' => $outcome['is_absent'],
            'is_theory_absent' => false,
            'is_practical_absent' => false,
            'mcq_marks' => null,
            'written_marks' => null,
            'practical_marks' => null,
            'total_marks' => $outcome['marks'],
            'components' => [],
            'grade_label' => $outcome['grade']?->letter_grade,
            'grade_color' => $outcome['grade']?->color,
            'grade_point' => $outcome['grade']?->grade_point,
            'is_top_scorer' => $best !== null
                && ! $outcome['is_absent']
                && (float) $outcome['marks'] === (float) $best['best_marks'],
            'best_marks' => $best['best_marks'] ?? null,
            'best_students' => $best['best_students'] ?? null,
            'papers' => $papers,
            'obtained_marks' => (float) ($first?->effective_marks ?? 0) + (float) ($second?->effective_marks ?? 0),
            'full_marks' => ($first ? $this->paperFullMarks($first, $context) : 0.0)
                + ($second ? $this->paperFullMarks($second, $context) : 0.0),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function paperFullMarks(StudentResult $result, array $context): float
    {
        return $result->resolveFullMarks((float) ($context['subjectConfigs']->get((int) $result->subject_id)?->total_marks ?: 100));
    }

    /**
     * The subject's name (marked "Additional" for the student's extra
     * optional subject) and where it sorts.
     *
     * @param  array<string, mixed>  $context
     * @param  Collection<int, StudentOptionalSubject>  $studentOptionalSelections
     * @return array{position_group: int, subject_name: string}
     */
    private function subjectPlacement(int $subjectId, string $subjectName, array $context, Collection $studentOptionalSelections, ?int $groupId): array
    {
        $isExtraOptional = $this->resolveIsExtraOptionalSubject->execute(
            $context['subjectTypeRecords'],
            $studentOptionalSelections,
            $subjectId,
            $groupId
        );

        $isMainOptional = ! $isExtraOptional
            && $studentOptionalSelections->firstWhere('subject_id', $subjectId)?->role === OptionalSubjectRole::MainOptional;

        return [
            'position_group' => match (true) {
                $isExtraOptional => 2,
                $isMainOptional => 1,
                default => 0,
            },
            'subject_name' => $isExtraOptional ? "{$subjectName} (Additional)" : $subjectName,
        ];
    }

    /**
     * One paper's own marks as the marks table shows them.
     *
     * @return array{is_absent: bool, is_theory_absent: bool, is_practical_absent: bool, mcq_marks: ?float, written_marks: ?float, practical_marks: ?float, total_marks: float, components: list<array<string, mixed>>}
     */
    private function paperColumns(StudentResult $result): array
    {
        // A result-rule result has marks even when this exam itself was missed.
        $ownResult = ! $result->hasWeightedResult();

        return [
            'is_absent' => $result->is_absent && $ownResult,
            'is_theory_absent' => $result->is_theory_absent && $ownResult,
            'is_practical_absent' => $result->is_practical_absent && $ownResult,
            'mcq_marks' => $result->mcq_marks,
            'written_marks' => $result->written_marks,
            'practical_marks' => $result->practical_marks,
            'total_marks' => $result->effective_marks,
            'components' => $this->components($result),
        ];
    }

    /**
     * @param  Collection<int, ExamSubjectMerge>  $merges
     * @return Collection<int, ExamSubjectMerge> subject_id → its merge
     */
    private function mergeBySubject(Collection $merges): Collection
    {
        $map = collect();

        foreach ($merges as $merge) {
            foreach ($merge->subjectIds() as $subjectId) {
                $map->put($subjectId, $merge);
            }
        }

        return $map;
    }

    /**
     * The result-rule components of a weighted result, each with the marks
     * it gave (out of the rule's full marks) — empty for an exam's own result.
     *
     * @return list<array{name: string, weight: float, marks: float, is_absent: bool, passed: ?bool}>
     */
    private function components(StudentResult $result): array
    {
        if (! $result->hasWeightedResult()) {
            return [];
        }

        return collect($result->result_breakdown ?? [])
            ->where('held', true)
            ->map(fn (array $component): array => [
                'name' => $component['name'],
                'weight' => (float) $component['effective_weight'],
                'marks' => (float) $component['marks'],
                'is_absent' => (bool) $component['is_absent'],
                'passed' => $component['passed'],
            ])
            ->values()
            ->all();
    }

    /**
     * Everything that is the same for every student of one exam + class
     * (subject configs, per-subject top scorers, the class topper, working
     * days). Kept on the instance so a whole-class marksheet PDF computes it
     * once instead of once per student.
     *
     * A merged subject's top scorer is keyed "merge:{id}", by its merged marks.
     *
     * @return array{subjectConfigs: Collection, subjectBestMap: array<int|string, array{best_marks: mixed, best_students: string}>, subjectTypeRecords: Collection, merges: Collection<int, ExamSubjectMerge>, topRanking: ?StudentMeritRanking, sessionYear: ?int, workingDays: int, attendanceSummaries: Collection<int, StudentAttendanceSummary>}
     */
    private function examContext(StudentMeritRanking $ranking): array
    {
        return $this->examContexts["{$ranking->exam_id}-{$ranking->class_id}"] ??= (function () use ($ranking): array {
            $examResults = StudentResult::where('exam_id', $ranking->exam_id)
                ->with(['student:id,user_id,current_group_id', 'student.user:id,name'])
                ->get();
            $allResults = $examResults->groupBy('subject_id');

            $subjectConfigs = ExamSubjectConfig::where('exam_id', $ranking->exam_id)->get()->keyBy('subject_id');
            $merges = ExamSubjectMerge::where('exam_id', $ranking->exam_id)->get();
            $subjectTypeRecords = ClassGroupSubject::recordsBySubjectForClass($ranking->class_id);

            $subjectBestMap = $this->mergedBestMap($merges, $examResults, $subjectConfigs, $subjectTypeRecords);
            foreach ($allResults as $subjectId => $results) {
                $bestMarks = $results->max(fn (StudentResult $r) => $r->effective_marks);
                $subjectBestMap[$subjectId] = [
                    'best_marks' => $bestMarks,
                    'best_students' => $results
                        ->filter(fn (StudentResult $r) => (float) $r->effective_marks === (float) $bestMarks && (! $r->is_absent || $r->hasWeightedResult()))
                        ->map(fn ($r) => $r->student?->user?->name)
                        ->filter()
                        ->implode(', '),
                ];
            }

            $sessionYear = $ranking->exam?->session_year;

            return [
                'subjectConfigs' => $subjectConfigs,
                'subjectBestMap' => $subjectBestMap,
                'subjectTypeRecords' => $subjectTypeRecords,
                'merges' => $merges,
                'topRanking' => StudentMeritRanking::where('exam_id', $ranking->exam_id)
                    ->where('class_id', $ranking->class_id)
                    ->where('class_rank', 1)
                    ->first(),
                'sessionYear' => $sessionYear,
                'workingDays' => Attendance::where('attendable_type', StudentProfile::class)
                    ->where('class_id', $ranking->class_id)
                    ->inSession($sessionYear)
                    ->distinct('date')
                    ->count('date'),
                'attendanceSummaries' => StudentAttendanceSummary::where('exam_id', $ranking->exam_id)
                    ->get()
                    ->keyBy('student_id'),
            ];
        })();
    }

    /**
     * Each merged subject's best merged marks in the exam and who scored them,
     * keyed "merge:{id}".
     *
     * @param  Collection<int, ExamSubjectMerge>  $merges
     * @param  Collection<int, StudentResult>  $examResults
     * @param  Collection<int, ExamSubjectConfig>  $subjectConfigs
     * @param  Collection<int, Collection<int, ClassGroupSubject>>  $subjectTypeRecords
     * @return array<string, array{best_marks: float, best_students: string}>
     */
    private function mergedBestMap(Collection $merges, Collection $examResults, Collection $subjectConfigs, Collection $subjectTypeRecords): array
    {
        if ($merges->isEmpty()) {
            return [];
        }

        $gradeScales = GradeScale::cached();
        $resultsByStudent = $examResults->groupBy('student_id')->map(fn (Collection $results): Collection => $results->keyBy('subject_id'));
        $bestMap = [];

        foreach ($merges as $merge) {
            [$firstId, $secondId] = $merge->subjectIds();

            $scores = $resultsByStudent
                ->map(function (Collection $results) use ($merge, $firstId, $secondId, $subjectConfigs, $gradeScales, $subjectTypeRecords): ?array {
                    $first = $results->get($firstId);
                    $second = $results->get($secondId);

                    if (! $first && ! $second) {
                        return null;
                    }

                    // The merge does not apply to a student whose group takes only one paper.
                    $groupId = ($first ?? $second)->student?->current_group_id;

                    if (ExamSubjectMerge::applyingToGroup(collect([$merge]), $subjectTypeRecords, $groupId !== null ? (int) $groupId : null)->isEmpty()) {
                        return null;
                    }

                    $outcome = $this->resolveMergedSubjectOutcome->execute($merge, $first, $second, $subjectConfigs->get($firstId), $subjectConfigs->get($secondId), $gradeScales);

                    return $outcome['is_absent'] ? null : [
                        'marks' => $outcome['marks'],
                        'name' => ($first ?? $second)->student?->user?->name,
                    ];
                })
                ->filter();

            if ($scores->isEmpty()) {
                continue;
            }

            $bestMarks = (float) $scores->max('marks');

            $bestMap["merge:{$merge->id}"] = [
                'best_marks' => $bestMarks,
                'best_students' => $scores->filter(fn (array $score): bool => (float) $score['marks'] === $bestMarks)->pluck('name')->filter()->implode(', '),
            ];
        }

        return $bestMap;
    }
}
