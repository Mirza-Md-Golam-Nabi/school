<?php

namespace App\Actions;

use App\Models\Exam;
use App\Models\ExamSubjectMerge;
use App\Models\SubjectMerge;
use Illuminate\Support\Facades\DB;

class CopySubjectMergesToExamAction
{
    /**
     * Gives the exam its class's default subject merges, replacing whatever
     * merges the exam had. Each copy has every rule filled in (School
     * Settings for the ones the class left empty), so later changes to the
     * class default or the settings never change this exam's result.
     */
    public function handle(Exam $exam): int
    {
        $merges = SubjectMerge::where('class_id', $exam->class_id)->get();

        DB::transaction(function () use ($exam, $merges): void {
            $exam->subjectMerges()->get()->each(fn (ExamSubjectMerge $merge) => $merge->delete());

            foreach ($merges as $merge) {
                $exam->subjectMerges()->create([
                    'name' => $merge->name,
                    'name_bn' => $merge->name_bn,
                    'first_subject_id' => $merge->first_subject_id,
                    'second_subject_id' => $merge->second_subject_id,
                    ...$merge->resolvedRules(),
                ]);
            }
        });

        return $merges->count();
    }
}
