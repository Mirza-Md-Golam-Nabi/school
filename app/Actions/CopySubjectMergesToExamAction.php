<?php

namespace App\Actions;

use App\Models\Exam;
use App\Models\ExamSubjectMerge;
use App\Models\SubjectMerge;
use BackedEnum;
use Illuminate\Support\Facades\DB;

class CopySubjectMergesToExamAction
{
    /**
     * The columns that make two merges the same.
     */
    private const COMPARED = ['name', 'name_bn', 'first_subject_id', 'second_subject_id', 'pass_rule', 'component_pass_rule', 'absent_rule', 'rounding'];

    /**
     * Gives the exam its class's default subject merges, replacing whatever
     * merges the exam had, and makes the exam follow the class default again
     * (later class changes reach it until it is changed on the exam itself).
     * Each copy has every rule filled in (School Settings for the ones the
     * class left empty). Merges already matching the default are left as
     * they are, so nothing is rewritten or logged for no change.
     */
    public function handle(Exam $exam): int
    {
        $defaults = SubjectMerge::where('class_id', $exam->class_id)
            ->orderBy('id')
            ->get()
            ->map(fn (SubjectMerge $merge): array => [
                'name' => $merge->name,
                'name_bn' => $merge->name_bn,
                'first_subject_id' => $merge->first_subject_id,
                'second_subject_id' => $merge->second_subject_id,
                ...$merge->resolvedRules(),
            ]);

        DB::transaction(function () use ($exam, $defaults): void {
            $current = $exam->subjectMerges()->orderBy('id')->get();

            $isAlreadyDefault = $current->map(fn (ExamSubjectMerge $merge): array => self::comparable($merge->only(self::COMPARED)))->all()
                === $defaults->map(fn (array $merge): array => self::comparable($merge))->all();

            if (! $isAlreadyDefault) {
                $current->each(fn (ExamSubjectMerge $merge) => $merge->delete());

                foreach ($defaults as $merge) {
                    $exam->subjectMerges()->create($merge);
                }
            }

            if ($exam->has_custom_subject_merges) {
                $exam->update(['has_custom_subject_merges' => false]);
            }
        });

        return $defaults->count();
    }

    /**
     * @param  array<string, mixed>  $merge
     * @return list<mixed>
     */
    private static function comparable(array $merge): array
    {
        return array_map(
            fn (string $column): mixed => match (true) {
                ($merge[$column] ?? null) instanceof BackedEnum => $merge[$column]->value,
                in_array($column, ['first_subject_id', 'second_subject_id'], true) => (int) $merge[$column],
                default => $merge[$column] ?? null,
            },
            self::COMPARED,
        );
    }
}
