<?php

namespace App\Actions;

use App\Enums\Grade;
use App\Models\ExamSubjectConfig;
use App\Models\StudentMeritRanking;
use App\Models\StudentResult;
use Illuminate\Support\Collection;

class BuildStudentMarksDetail
{
    /**
     * Build the per-subject marks rows (own/contributed/final, grade, top-scorer)
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
            ->with(['subject', 'contributionSourceExamType'])
            ->get()
            ->keyBy('subject_id');

        $subjectConfigs = ExamSubjectConfig::where('exam_id', $ranking->exam_id)
            ->get()
            ->keyBy('subject_id');

        $allResults = StudentResult::where('exam_id', $ranking->exam_id)
            ->with('student.user')
            ->get()
            ->groupBy('subject_id');

        $subjectBestMap = [];
        foreach ($allResults as $subjectId => $results) {
            $bestMarks = $results->max(fn (StudentResult $r) => $r->effective_marks);
            $subjectBestMap[$subjectId] = [
                'best_marks' => $bestMarks,
                'best_students' => $results
                    ->filter(fn (StudentResult $r) => (float) $r->effective_marks === (float) $bestMarks && ! $r->is_absent)
                    ->map(fn ($r) => $r->student?->user?->name)
                    ->filter()
                    ->implode(', '),
            ];
        }

        $rows = $myResults->map(function (StudentResult $result) use ($subjectConfigs, $subjectBestMap): array {
            $subjectId = $result->subject_id;
            $config = $subjectConfigs->get($subjectId);
            $ownTotal = $config?->total_marks ?: 100;
            $fullMarks = $result->resolveFullMarks((float) $ownTotal);
            $percentage = (! $result->is_absent && $fullMarks > 0)
                ? ($result->effective_marks / $fullMarks) * 100
                : 0.0;
            $grade = (! $result->is_absent && $result->effective_marks > 0)
                ? Grade::fromMarks($percentage)
                : null;
            $best = $subjectBestMap[$subjectId] ?? null;

            $contribution = null;

            if (! $result->is_absent && $result->contribution_percent) {
                $contribution = [
                    'own_marks' => $result->total_marks,
                    'own_total' => $ownTotal,
                    'contributed_marks' => $result->contributed_marks,
                    'source_name' => $result->contributionSourceExamType?->name ?? 'Source Exam',
                    'source_percent' => $result->contribution_percent,
                    'breakdown' => collect($result->contribution_source_breakdown ?? [])
                        ->map(fn (array $item) => $item['marks'])
                        ->implode(', '),
                ];
            }

            return [
                'subject_name' => $result->subject?->name ?? '—',
                'is_absent' => $result->is_absent,
                'mcq_marks' => $result->mcq_marks,
                'written_marks' => $result->written_marks,
                'practical_marks' => $result->practical_marks,
                'total_marks' => $result->effective_marks,
                'grade_label' => $grade?->getLabel(),
                'grade_color' => $grade?->getColor(),
                'is_top_scorer' => $best !== null
                    && ! $result->is_absent
                    && (float) $result->effective_marks === (float) $best['best_marks'],
                'best_marks' => $best['best_marks'] ?? null,
                'best_students' => $best['best_students'] ?? null,
                'contribution' => $contribution,
            ];
        })->values();

        $overallGrade = Grade::fromGpa((float) $ranking->gpa);

        $summary = [
            'total_marks' => $ranking->total_marks,
            'class_rank' => $ranking->class_rank,
            'section_rank' => $ranking->section_rank,
            'gpa' => number_format((float) $ranking->gpa, 2),
            'overall_grade_label' => $overallGrade->getLabel(),
            'overall_grade_color' => $overallGrade->getColor(),
        ];

        return ['rows' => $rows, 'summary' => $summary];
    }
}
