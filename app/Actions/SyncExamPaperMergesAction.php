<?php

namespace App\Actions;

use App\Models\Exam;
use App\Models\ExamSubjectMerge;
use App\Models\Subject;
use App\Models\SubjectMerge;
use Illuminate\Support\Collection;

/**
 * Keeps an exam's paper merges in step with its subjects: when a paper
 * joins the exam and completes a pair (Biology 1st + 2nd Paper), the two are
 * merged into their main subject (Biology) with the School Settings rules;
 * when one of the papers leaves the exam, that merge goes too. Merges the
 * admin made by hand, and the rules of an existing paper merge, are left
 * alone — and a paper merge the admin deleted stays deleted, because only a
 * paper joining the exam creates one.
 */
class SyncExamPaperMergesAction
{
    /**
     * @param  array<int, int>  $addedSubjectIds  subjects that just joined the exam; only pairs they complete are merged
     */
    public function handle(Exam $exam, array $addedSubjectIds = []): void
    {
        $configuredSubjectIds = $exam->subjectConfigs()->pluck('subject_id')->map(fn (mixed $id): int => (int) $id);
        $merges = $exam->subjectMerges()->get();

        $merges
            ->filter(fn (ExamSubjectMerge $merge): bool => self::isPaperPair($merge->subjectIds())
                && collect($merge->subjectIds())->diff($configuredSubjectIds)->isNotEmpty())
            ->each(fn (ExamSubjectMerge $merge) => $merge->delete());

        $mergedSubjectIds = $exam->subjectMerges()->get()
            ->flatMap(fn (ExamSubjectMerge $merge): array => $merge->subjectIds());

        if ($addedSubjectIds === []) {
            return;
        }

        $addedSubjectIds = array_map('intval', $addedSubjectIds);

        foreach ($this->paperMergesFor($configuredSubjectIds) as $paperMerge) {
            if (array_intersect([(int) $paperMerge['first_subject_id'], (int) $paperMerge['second_subject_id']], $addedSubjectIds) === []) {
                continue;
            }

            if ($mergedSubjectIds->intersect([$paperMerge['first_subject_id'], $paperMerge['second_subject_id']])->isNotEmpty()) {
                continue;
            }

            $exam->subjectMerges()->create($paperMerge);
        }
    }

    /**
     * The merges the given exam subjects call for: one per main subject whose
     * two papers are both among them, named after the main subject.
     *
     * @param  Collection<int, int>  $subjectIds
     * @return Collection<int, array<string, mixed>>
     */
    public function paperMergesFor(Collection $subjectIds): Collection
    {
        $rules = SubjectMerge::defaultRules();

        return Subject::query()
            ->with('parent')
            ->whereKey($subjectIds->all())
            ->whereNotNull('parent_subject_id')
            ->ordered()
            ->get()
            ->groupBy('parent_subject_id')
            ->filter(fn (Collection $papers): bool => $papers->count() === 2)
            ->map(function (Collection $papers) use ($rules): array {
                [$firstPaper, $secondPaper] = $papers->values()->all();

                return [
                    'name' => $firstPaper->parent?->name ?? $firstPaper->name,
                    'name_bn' => $firstPaper->parent?->name_bn,
                    'first_subject_id' => $firstPaper->id,
                    'second_subject_id' => $secondPaper->id,
                    ...$rules,
                ];
            })
            ->values();
    }

    /**
     * Whether the two subjects are papers of the same main subject.
     *
     * @param  array{0: int, 1: int}  $subjectIds
     */
    private static function isPaperPair(array $subjectIds): bool
    {
        $parentIds = Subject::withTrashed()->whereKey($subjectIds)->pluck('parent_subject_id');

        return $parentIds->count() === 2
            && $parentIds->filter()->count() === 2
            && $parentIds->unique()->count() === 1;
    }
}
