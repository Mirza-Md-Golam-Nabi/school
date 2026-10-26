<?php

namespace App\Actions;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\ClassGroupSubject;
use App\Models\ExamSubjectConfig;
use App\Models\GradeScale;
use App\Models\StudentMeritRanking;
use App\Models\StudentOptionalSubject;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use Illuminate\Support\Collection;

class BuildStudentMarksDetail
{
    /** @var array<string, array<string, mixed>> */
    private array $examContexts = [];

    public function __construct(
        private readonly ResolveIsExtraOptionalSubject $resolveIsExtraOptionalSubject,
        private readonly ResolveSubjectOutcome $resolveSubjectOutcome,
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
        $myResults = StudentResult::where('exam_id', $ranking->exam_id)
            ->where('student_id', $ranking->student_id)
            ->with('subject')
            ->get()
            ->keyBy('subject_id');

        [
            'subjectConfigs' => $subjectConfigs,
            'subjectBestMap' => $subjectBestMap,
            'subjectTypeRecords' => $subjectTypeRecords,
            'topRanking' => $topRanking,
            'sessionYear' => $sessionYear,
            'workingDays' => $workingDays,
        ] = $this->examContext($ranking);

        $gradeScales = GradeScale::cached();

        $groupId = $ranking->student?->current_group_id
            ? (int) $ranking->student->current_group_id
            : null;

        $studentOptionalSelections = StudentOptionalSubject::where('class_id', $ranking->class_id)
            ->where('student_id', $ranking->student_id)
            ->get();

        $rows = $myResults->map(function (StudentResult $result) use ($subjectConfigs, $subjectBestMap, $gradeScales, $subjectTypeRecords, $studentOptionalSelections, $groupId): array {
            $subjectId = $result->subject_id;
            $grade = $this->resolveSubjectOutcome->execute($result, $subjectConfigs->get($subjectId), $gradeScales)['grade'];
            $best = $subjectBestMap[$subjectId] ?? null;

            $isExtraOptional = $this->resolveIsExtraOptionalSubject->execute(
                $subjectTypeRecords,
                $studentOptionalSelections,
                (int) $subjectId,
                $groupId
            );

            $subjectName = $result->subject?->display_name ?? '—';

            if ($isExtraOptional) {
                $subjectName .= ' (Additional)';
            }

            return [
                'subject_name' => $subjectName,
                // A result-rule result has marks even when this exam itself was missed.
                'is_absent' => $result->is_absent && ! $result->hasWeightedResult(),
                'mcq_marks' => $result->mcq_marks,
                'written_marks' => $result->written_marks,
                'practical_marks' => $result->practical_marks,
                'total_marks' => $result->effective_marks,
                'grade_label' => $grade?->letter_grade,
                'grade_color' => $grade?->color,
                'is_top_scorer' => $best !== null
                    && (! $result->is_absent || $result->hasWeightedResult())
                    && (float) $result->effective_marks === (float) $best['best_marks'],
                'best_marks' => $best['best_marks'] ?? null,
                'best_students' => $best['best_students'] ?? null,
                'components' => $this->components($result),
            ];
        })->values();

        $overallGrade = GradeScale::fromGpa((float) $ranking->gpa, $gradeScales);

        // "1st position" means class_rank = 1 (the top exam performer), never roll_no = 1.
        $topRankGrade = $topRanking ? GradeScale::fromGpa((float) $topRanking->gpa, $gradeScales) : null;

        $presentDays = Attendance::where('attendable_type', StudentProfile::class)
            ->where('attendable_id', $ranking->student_id)
            ->where('class_id', $ranking->class_id)
            ->countedPresent(StudentProfile::class)
            ->inYear($sessionYear)
            ->count();

        $summary = [
            'total_marks' => $ranking->total_marks,
            'class_rank' => $ranking->class_rank,
            'section_rank' => $ranking->section_rank,
            'gpa' => number_format((float) $ranking->gpa, 2),
            'overall_grade_label' => $overallGrade?->letter_grade,
            'overall_grade_color' => $overallGrade?->color,
            'top_rank_total_marks' => $topRanking?->total_marks,
            'top_rank_gpa' => $topRanking ? number_format((float) $topRanking->gpa, 2) : null,
            'top_rank_grade_label' => $topRankGrade?->letter_grade,
            'working_days' => $workingDays,
            'present_days' => $presentDays,
        ];

        return ['rows' => $rows, 'summary' => $summary];
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
     * @return array{subjectConfigs: Collection, subjectBestMap: array<int, array{best_marks: mixed, best_students: string}>, subjectTypeRecords: Collection, topRanking: ?StudentMeritRanking, sessionYear: ?int, workingDays: int}
     */
    private function examContext(StudentMeritRanking $ranking): array
    {
        return $this->examContexts["{$ranking->exam_id}-{$ranking->class_id}"] ??= (function () use ($ranking): array {
            $allResults = StudentResult::where('exam_id', $ranking->exam_id)
                ->with(['student:id,user_id', 'student.user:id,name'])
                ->get(['id', 'subject_id', 'student_id', 'total_marks', 'final_marks', 'is_absent'])
                ->groupBy('subject_id');

            $subjectBestMap = [];
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
                'subjectConfigs' => ExamSubjectConfig::where('exam_id', $ranking->exam_id)->get()->keyBy('subject_id'),
                'subjectBestMap' => $subjectBestMap,
                'subjectTypeRecords' => ClassGroupSubject::where('class_id', $ranking->class_id)->get()->groupBy('subject_id'),
                'topRanking' => StudentMeritRanking::where('exam_id', $ranking->exam_id)
                    ->where('class_id', $ranking->class_id)
                    ->where('class_rank', 1)
                    ->first(),
                'sessionYear' => $sessionYear,
                'workingDays' => Attendance::where('attendable_type', StudentProfile::class)
                    ->where('class_id', $ranking->class_id)
                    ->inYear($sessionYear)
                    ->distinct('date')
                    ->count('date'),
            ];
        })();
    }
}
