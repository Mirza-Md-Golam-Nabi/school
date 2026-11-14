<?php

namespace App\Traits;

use App\Enums\MergedAbsentRule;
use App\Enums\MergedComponentPassRule;
use App\Enums\MergedPassRule;
use App\Enums\ResultRounding;
use App\Models\ClassGroupSubject;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Shared by SubjectMerge (a class's default) and ExamSubjectMerge (an exam's
 * own copy): two subjects — the 1st and 2nd paper — counted as one subject
 * in the result, with the rules that decide its pass and rounding.
 */
trait MergesTwoSubjects
{
    public function initializeMergesTwoSubjects(): void
    {
        $this->mergeCasts([
            'first_subject_id' => 'integer',
            'second_subject_id' => 'integer',
            'pass_rule' => MergedPassRule::class,
            'component_pass_rule' => MergedComponentPassRule::class,
            'absent_rule' => MergedAbsentRule::class,
            'rounding' => ResultRounding::class,
        ]);
    }

    public function firstSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'first_subject_id');
    }

    public function secondSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'second_subject_id');
    }

    /**
     * @return array{0: int, 1: int}
     */
    public function subjectIds(): array
    {
        return [(int) $this->first_subject_id, (int) $this->second_subject_id];
    }

    public function getDisplayNameAttribute(): string
    {
        if (app()->getLocale() === 'bn' && filled($this->name_bn)) {
            return $this->name_bn;
        }

        return (string) $this->name;
    }

    /**
     * Why these two subjects cannot be merged in this class, or null when
     * they can: two different subjects, both taught in the class with the
     * same subject type, and neither already in another merge.
     *
     * @param  Collection<int, int>  $takenSubjectIds  subjects already in the class's / exam's other merges
     */
    public static function mergeProblem(int $classId, mixed $firstSubjectId, mixed $secondSubjectId, Collection $takenSubjectIds): ?string
    {
        if (blank($firstSubjectId) || blank($secondSubjectId)) {
            return null;
        }

        $firstSubjectId = (int) $firstSubjectId;
        $secondSubjectId = (int) $secondSubjectId;

        if ($firstSubjectId === $secondSubjectId) {
            return '১ম পত্র আর ২য় পত্র একই সাবজেক্ট হতে পারবে না।';
        }

        if ($takenSubjectIds->contains($firstSubjectId) || $takenSubjectIds->contains($secondSubjectId)) {
            return 'এই সাবজেক্ট অন্য একটা মার্জে আগেই আছে।';
        }

        $typesBySubject = ClassGroupSubject::where('class_id', $classId)
            ->whereIn('subject_id', [$firstSubjectId, $secondSubjectId])
            ->get()
            ->groupBy('subject_id')
            ->map(fn (Collection $records): array => $records->map(fn (ClassGroupSubject $record): string => $record->subject_type->value)->unique()->sort()->values()->all());

        if (! $typesBySubject->has($firstSubjectId) || ! $typesBySubject->has($secondSubjectId)) {
            return 'দুটো সাবজেক্টই এই ক্লাসে থাকতে হবে।';
        }

        if ($typesBySubject->get($firstSubjectId) !== $typesBySubject->get($secondSubjectId)) {
            return 'দুটো সাবজেক্টের ধরন (Compulsory / Optional) একই হতে হবে।';
        }

        return null;
    }
}
