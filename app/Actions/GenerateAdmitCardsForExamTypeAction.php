<?php

namespace App\Actions;

use App\Models\AdmitCard;
use App\Models\Exam;
use App\Models\ExamType;
use Illuminate\Support\Facades\Storage;

class GenerateAdmitCardsForExamTypeAction
{
    /**
     * Generate admit cards for every exam of this type, across all classes —
     * i.e. picking just an exam name (e.g. "Half Yearly") generates cards for
     * every class's instance of that exam, not one class at a time.
     *
     * Only one exam type's admit cards exist at a time: switching to a
     * different exam type first wipes every existing admit card (files
     * included, regardless of generated/pending status) for any other exam
     * type before generating the new batch.
     *
     * @return array{created: int, skipped: int}
     */
    public function handle(ExamType $examType, ?int $generatedBy = null, string $pageSize = 'A4'): array
    {
        $this->clearAdmitCardsForOtherExamTypes($examType);

        $created = 0;
        $skipped = 0;

        $exams = Exam::where('exam_type_id', $examType->id)->get();

        foreach ($exams as $exam) {
            $result = app(GenerateAdmitCardsForExamAction::class)->handle($exam, $generatedBy, $pageSize);

            $created += $result['created'];
            $skipped += $result['skipped'];
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    protected function clearAdmitCardsForOtherExamTypes(ExamType $examType): void
    {
        AdmitCard::whereHas('exam', fn ($query) => $query->where('exam_type_id', '!=', $examType->id))
            ->get()
            ->each(function (AdmitCard $admitCard) {
                if ($admitCard->file_path && Storage::disk('local')->exists($admitCard->file_path)) {
                    Storage::disk('local')->delete($admitCard->file_path);
                }

                $admitCard->delete();
            });
    }
}
