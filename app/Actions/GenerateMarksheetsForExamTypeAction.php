<?php

namespace App\Actions;

use App\Models\Exam;
use App\Models\ExamType;

class GenerateMarksheetsForExamTypeAction
{
    /**
     * Generate marksheets for every exam of this type, across all classes — i.e.
     * picking just an exam name (e.g. "Half Yearly") generates marksheets for
     * every class's instance of that exam, not one class at a time.
     *
     * Unlike admit cards, marksheets for other exam types are never cleared —
     * a school keeps Class Test, Half Yearly, and Annual marksheets side by side.
     *
     * @return array{created: int, regenerated: int, skipped_no_ranking: int}
     */
    public function handle(ExamType $examType, ?int $generatedBy = null): array
    {
        $created = 0;
        $regenerated = 0;
        $skippedNoRanking = 0;

        $exams = Exam::where('exam_type_id', $examType->id)->get();

        foreach ($exams as $exam) {
            $result = app(GenerateMarksheetsForExamAction::class)->handle($exam, $generatedBy);

            $created += $result['created'];
            $regenerated += $result['regenerated'];
            $skippedNoRanking += $result['skipped_no_ranking'];
        }

        return [
            'created' => $created,
            'regenerated' => $regenerated,
            'skipped_no_ranking' => $skippedNoRanking,
        ];
    }
}
