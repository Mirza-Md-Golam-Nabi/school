<?php

namespace App\Actions;

use App\Enums\AdmitCardDesign;
use App\Models\AdmitCard;
use App\Models\Exam;
use App\Models\ExamType;
use Illuminate\Support\Collection;
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
     * With the routine design, a class whose exam has no Exam Schedule gets
     * no cards; its name is listed in `classes_without_routine`.
     *
     * @return array{created: int, updated: int, skipped: int, classes_without_routine: list<string>}
     */
    public function handle(ExamType $examType, ?int $generatedBy = null, string $pageSize = 'A4', AdmitCardDesign $design = AdmitCardDesign::Simple): array
    {
        $this->clearAdmitCardsForOtherExamTypes($examType);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $classesWithoutRoutine = [];

        $exams = Exam::where('exam_type_id', $examType->id)->with('class:id,name')->get();

        foreach ($exams as $exam) {
            $result = app(GenerateAdmitCardsForExamAction::class)->handle($exam, $generatedBy, $pageSize, $design);

            $created += $result['created'];
            $updated += $result['updated'];
            $skipped += $result['skipped'];

            if ($result['without_routine']) {
                $classesWithoutRoutine[] = $exam->class?->name ?? "Class #{$exam->class_id}";
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'classes_without_routine' => $classesWithoutRoutine];
    }

    protected function clearAdmitCardsForOtherExamTypes(ExamType $examType): void
    {
        $clearedCount = 0;

        AdmitCard::whereIn('exam_id', Exam::where('exam_type_id', '!=', $examType->id)->select('id'))
            ->select(['id', 'file_path'])
            ->chunkById(500, function (Collection $admitCards) use (&$clearedCount): void {
                foreach ($admitCards as $admitCard) {
                    if ($admitCard->file_path && Storage::disk('local')->exists($admitCard->file_path)) {
                        Storage::disk('local')->delete($admitCard->file_path);
                    }

                    $admitCard->delete();
                    $clearedCount++;
                }
            });

        if ($clearedCount === 0) {
            return;
        }

        $this->logClear($examType, $clearedCount);
    }

    private function logClear(ExamType $examType, int $clearedCount): void
    {
        activity('admit_card_generation')
            ->performedOn($examType)
            ->event('deleted')
            ->withProperties([
                'attributes' => [
                    'exam_type_id' => $examType->id,
                    'exam_type_id_label' => $examType->name,
                    'cleared_count' => $clearedCount,
                ],
            ])
            ->log("Cleared {$clearedCount} existing admit card(s) from other exam types before generating \"{$examType->name}\" admit cards.");
    }
}
