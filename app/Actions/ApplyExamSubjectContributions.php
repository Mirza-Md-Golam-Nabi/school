<?php

namespace App\Actions;

use App\Enums\CountMethod;
use App\Models\Exam;
use App\Models\ExamContributeRule;
use App\Models\ExamSubjectConfig;
use App\Models\ExamTypeConfig;
use App\Models\StudentResult;

class ApplyExamSubjectContributions
{
    /**
     * Blend marks from a source exam type (e.g. Class Test) into this target exam
     * (e.g. Half Yearly), per subject, wherever a contribution rule applies and the
     * subject's source configs are marked as contributing. Persists the blended
     * result onto each student's `StudentResult` row for this exam so ranking/GPA
     * and result display can use `final_marks` in place of `total_marks`.
     */
    public function execute(Exam $exam): void
    {
        $rule = ExamContributeRule::where('target_exam_type_id', $exam->exam_type_id)
            ->where('class_id', $exam->class_id)
            ->where('session_year', $exam->session_year)
            ->first();

        if (! $rule) {
            return;
        }

        $sourceExamIds = Exam::where('exam_type_id', $rule->source_exam_type_id)
            ->where('class_id', $exam->class_id)
            ->where('session_year', $exam->session_year)
            ->pluck('id');

        if ($sourceExamIds->isEmpty()) {
            return;
        }

        $targetSubjectConfigs = ExamSubjectConfig::where('exam_id', $exam->id)->get()->keyBy('subject_id');

        if ($targetSubjectConfigs->isEmpty()) {
            return;
        }

        $sourceTypeConfig = ExamTypeConfig::where('exam_type_id', $rule->source_exam_type_id)->first();
        $bestCount = $sourceTypeConfig?->count_method === CountMethod::BestN
            ? ($sourceTypeConfig->best_n_count ?? PHP_INT_MAX)
            : PHP_INT_MAX;

        // subject_id → Collection<ExamSubjectConfig> across the eligible source exam instances
        $sourceSubjectConfigs = ExamSubjectConfig::whereIn('exam_id', $sourceExamIds)
            ->where('contributes_to_target', true)
            ->get()
            ->groupBy('subject_id');

        $contributionPercent = (int) $rule->contribution_percent;

        foreach ($targetSubjectConfigs as $subjectId => $targetConfig) {
            $eligibleSourceConfigs = $sourceSubjectConfigs->get($subjectId);

            if (! $eligibleSourceConfigs || $eligibleSourceConfigs->isEmpty()) {
                continue;
            }

            $eligibleSourceExamIds = $eligibleSourceConfigs->pluck('exam_id');

            $sourceResultsByStudent = StudentResult::whereIn('exam_id', $eligibleSourceExamIds)
                ->where('subject_id', $subjectId)
                ->where('is_absent', false)
                ->get()
                ->groupBy('student_id');

            $targetResults = StudentResult::where('exam_id', $exam->id)
                ->where('subject_id', $subjectId)
                ->where('is_absent', false)
                ->get();

            foreach ($targetResults as $targetResult) {
                $studentSourceResults = $sourceResultsByStudent->get($targetResult->student_id);

                if (! $studentSourceResults || $studentSourceResults->isEmpty()) {
                    continue;
                }

                $scored = $studentSourceResults
                    ->map(function (StudentResult $result) use ($eligibleSourceConfigs) {
                        $config = $eligibleSourceConfigs->firstWhere('exam_id', $result->exam_id);
                        $fullMarks = (float) ($config?->total_marks ?? 0);

                        if ($fullMarks <= 0) {
                            return null;
                        }

                        return [
                            'exam_id' => $result->exam_id,
                            'marks' => (float) $result->total_marks,
                            'total_marks' => $fullMarks,
                            'percentage' => ($result->total_marks / $fullMarks) * 100,
                        ];
                    })
                    ->filter()
                    ->values();

                if ($scored->isEmpty()) {
                    continue;
                }

                $best = $scored->sortByDesc('percentage')->take($bestCount)->values();
                $sourcePercentage = (float) $best->avg('percentage');

                $ownTotal = (float) $targetConfig->total_marks;
                $ownMarks = (float) $targetResult->total_marks;
                $ownPercentage = $ownTotal > 0 ? ($ownMarks / $ownTotal) * 100 : 0.0;

                $finalPercentage = $ownPercentage * (100 - $contributionPercent) / 100
                    + $sourcePercentage * $contributionPercent / 100;

                $grandTotal = $contributionPercent < 100
                    ? $ownTotal / (1 - $contributionPercent / 100)
                    : $ownTotal;

                $targetResult->update([
                    'contributed_marks' => round($sourcePercentage / 100 * ($grandTotal - $ownTotal), 2),
                    'contribution_percent' => $contributionPercent,
                    'contribution_source_exam_type_id' => $rule->source_exam_type_id,
                    'contribution_source_breakdown' => $best->map(fn (array $item): array => [
                        'exam_id' => $item['exam_id'],
                        'marks' => $item['marks'],
                        'total_marks' => $item['total_marks'],
                    ])->all(),
                    'final_marks' => round($finalPercentage / 100 * $grandTotal, 2),
                ]);
            }
        }
    }
}
