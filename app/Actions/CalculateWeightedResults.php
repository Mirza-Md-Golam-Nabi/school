<?php

namespace App\Actions;

use App\Enums\CountMethod;
use App\Enums\ResultRounding;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ResultRule;
use App\Models\StudentResult;
use Illuminate\Support\Collection;

class CalculateWeightedResults
{
    /**
     * Builds this exam's result from several exams wherever a ResultRule
     * makes the exam's type its target for the exam's class — e.g. "1st
     * Semester 25% + 2nd Semester 25% + Final 50%, out of 100". The only
     * place that does this arithmetic.
     *
     * Per student and subject, each component is the student's percentage in
     * that exam type (several exams of the type — CT-1, CT-2… — averaged by
     * its count method; absent counts as 0). A component in which the subject
     * was not examined at all (or is switched off with `contributes_to_target`)
     * gives its share to the others in proportion. The weighted sum, out of
     * the rule's full marks and rounded only at the end, is saved as the
     * result's `final_marks`, with every component in `result_breakdown`.
     *
     * Every run starts from scratch, so marks or rules changed since the last
     * run never leave an old weighted result behind. Once the exam is
     * published it keeps the copy of the rule it was calculated with.
     */
    public function execute(Exam $exam): void
    {
        activity()->withoutLogs(function () use ($exam): void {
            StudentResult::where('exam_id', $exam->id)->update([
                'result_breakdown' => null,
                'final_marks' => null,
                'final_full_marks' => null,
            ]);

            $snapshot = $this->resolveSnapshot($exam);

            if ($snapshot) {
                $this->apply($exam, $snapshot);
            }
        });
    }

    /**
     * The rule this exam is calculated with: the copy kept on it once it is
     * published, otherwise the rule as it is now (saved as the new copy).
     *
     * @return array<string, mixed>|null
     */
    private function resolveSnapshot(Exam $exam): ?array
    {
        if ($exam->is_published && $exam->result_rule_snapshot) {
            return $exam->result_rule_snapshot;
        }

        $snapshot = ResultRule::forExam($exam)?->snapshot();

        $exam->update(['result_rule_snapshot' => $snapshot]);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function apply(Exam $exam, array $snapshot): void
    {
        $components = collect($snapshot['components']);

        // exam_type_id → the exams of that type this result draws on. The
        // target's own share always comes from this exam alone.
        $examIdsByType = Exam::where('class_id', $exam->class_id)
            ->where('session_year', $exam->session_year)
            ->whereIn('exam_type_id', $components->pluck('exam_type_id'))
            ->whereKeyNot($exam->id)
            ->get(['id', 'exam_type_id'])
            ->groupBy('exam_type_id')
            ->map(fn (Collection $exams): array => $exams->pluck('id')->all())
            ->put($exam->exam_type_id, [$exam->id]);

        $examIds = $examIdsByType->flatten()->unique()->values();

        // exam_id → subject_id → config
        $configs = ExamSubjectConfig::whereIn('exam_id', $examIds)
            ->get()
            ->groupBy('exam_id')
            ->map(fn (Collection $examConfigs): Collection => $examConfigs->keyBy('subject_id'));

        // "exam_id-student_id-subject_id" → the other exams' results
        $otherResults = StudentResult::whereIn('exam_id', $examIds->reject(fn (int $id): bool => $id === $exam->id))
            ->get()
            ->keyBy(fn (StudentResult $result): string => "{$result->exam_id}-{$result->student_id}-{$result->subject_id}");

        $fullMarks = (float) $snapshot['full_marks'];
        $rounding = ResultRounding::tryFrom($snapshot['rounding']) ?? ResultRounding::Decimal;

        $targetResults = StudentResult::where('exam_id', $exam->id)->get();

        foreach ($targetResults as $targetResult) {
            if (! $configs->get($exam->id)?->has($targetResult->subject_id)) {
                continue;
            }

            $parts = $components->map(fn (array $component): array => $this->componentPart(
                $component,
                $examIdsByType->get($component['exam_type_id'], []),
                $exam,
                $targetResult,
                $configs,
                $otherResults,
            ));

            $heldWeight = $parts->where('held', true)->sum('weight_percent');

            if ($heldWeight <= 0) {
                continue;
            }

            $parts = $parts->map(function (array $part) use ($heldWeight, $fullMarks): array {
                if (! $part['held']) {
                    return $part + ['effective_weight' => 0.0, 'marks' => 0.0];
                }

                $effectiveWeight = $part['weight_percent'] / $heldWeight * 100;

                return $part + [
                    'effective_weight' => round($effectiveWeight, 2),
                    'marks' => $part['percentage'] / 100 * $effectiveWeight / 100 * $fullMarks,
                ];
            });

            $finalMarks = $rounding->apply($parts->sum('marks'));

            $targetResult->update([
                'result_breakdown' => $parts->map(fn (array $part): array => [
                    ...$part,
                    'percentage' => round($part['percentage'], 2),
                    'marks' => round($part['marks'], 2),
                ])->values()->all(),
                'final_marks' => $finalMarks,
                'final_full_marks' => $fullMarks,
            ]);
        }
    }

    /**
     * One component of one student's subject: the percentage the student
     * scored in it, and — where it must be passed on its own — whether they did.
     *
     * @param  array<string, mixed>  $component
     * @param  array<int, int>  $examIds
     * @param  Collection<int, Collection<int, ExamSubjectConfig>>  $configs
     * @param  Collection<string, StudentResult>  $otherResults
     * @return array<string, mixed>
     */
    private function componentPart(
        array $component,
        array $examIds,
        Exam $targetExam,
        StudentResult $targetResult,
        Collection $configs,
        Collection $otherResults,
    ): array {
        $subjectId = $targetResult->subject_id;

        $part = [
            'exam_type_id' => $component['exam_type_id'],
            'name' => $component['name'],
            'weight_percent' => (float) $component['weight_percent'],
            'must_pass' => (bool) $component['must_pass'],
        ];

        $scored = collect($examIds)
            ->map(function (int $examId) use ($targetExam, $targetResult, $configs, $otherResults, $subjectId): ?array {
                $config = $configs->get($examId)?->get($subjectId);
                $isTarget = $examId === $targetExam->id;

                // Not examined in this subject, or kept out of the result on purpose.
                if (! $config || (float) $config->total_marks <= 0 || (! $isTarget && ! $config->contributes_to_target)) {
                    return null;
                }

                $result = $isTarget ? $targetResult : $otherResults->get("{$examId}-{$targetResult->student_id}-{$subjectId}");
                $isAbsent = ! $result || $result->is_absent;
                $marks = $isAbsent ? 0.0 : (float) $result->total_marks;

                return [
                    'exam_id' => $examId,
                    'marks' => $marks,
                    'total_marks' => (float) $config->total_marks,
                    'is_absent' => $isAbsent,
                    'percentage' => $marks / (float) $config->total_marks * 100,
                    'pass_percentage' => $config->passPercentage(),
                    'passed' => $result !== null && $config->isPassedBy($result),
                ];
            })
            ->filter()
            ->values();

        if ($scored->isEmpty()) {
            return $part + ['held' => false, 'percentage' => 0.0, 'is_absent' => false, 'passed' => null, 'exams' => []];
        }

        $counted = $scored->sortByDesc('percentage')->values();

        if ($component['count_method'] === CountMethod::BestN->value && $component['best_n_count']) {
            $counted = $counted->take((int) $component['best_n_count']);
        }

        $percentage = (float) $counted->avg('percentage');

        // One exam: its own pass marks (MCQ / written / practical included).
        // Several: the combined percentage against their pass percentage.
        $passed = match (true) {
            ! $component['must_pass'] => null,
            $counted->count() === 1 => $counted->first()['passed'],
            default => $percentage >= (float) $counted->avg('pass_percentage'),
        };

        return $part + [
            'held' => true,
            'percentage' => $percentage,
            'is_absent' => $counted->every(fn (array $exam): bool => $exam['is_absent']),
            'passed' => $passed,
            'exams' => $counted->map(fn (array $exam): array => [
                'exam_id' => $exam['exam_id'],
                'marks' => $exam['marks'],
                'total_marks' => $exam['total_marks'],
                'is_absent' => $exam['is_absent'],
            ])->all(),
        ];
    }
}
