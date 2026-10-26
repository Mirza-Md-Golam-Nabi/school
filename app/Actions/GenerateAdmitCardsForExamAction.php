<?php

namespace App\Actions;

use App\Enums\AdmitCardDesign;
use App\Jobs\GenerateAdmitCardPdfJob;
use App\Models\AdmitCard;
use App\Models\Exam;
use App\Models\StudentProfile;

class GenerateAdmitCardsForExamAction
{
    /**
     * Create an admit card record for every active student in the exam's class
     * and dispatch a PDF generation job for each. A student who already has a
     * card for this exam (unique student_id/exam_id) is skipped — unless the
     * card is of another design, in which case it is switched to this one and
     * generated again.
     *
     * A card with the routine is never generated for an exam without an Exam
     * Schedule: nothing is created, and `without_routine` says so.
     *
     * @return array{created: int, updated: int, skipped: int, without_routine: bool}
     */
    public function handle(Exam $exam, ?int $generatedBy = null, string $pageSize = 'A4', AdmitCardDesign $design = AdmitCardDesign::Simple): array
    {
        if ($design === AdmitCardDesign::WithRoutine && ! $exam->schedules()->exists()) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'without_routine' => true];
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $students = StudentProfile::active()
            ->where('current_class_id', $exam->class_id)
            ->get(['id']);

        $existingCards = AdmitCard::where('exam_id', $exam->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->get(['id', 'student_id', 'design'])
            ->keyBy('student_id');

        foreach ($students as $student) {
            $existingCard = $existingCards->get($student->id);

            if ($existingCard && $existingCard->design === $design) {
                $skipped++;

                continue;
            }

            if ($existingCard) {
                $existingCard->update(['design' => $design, 'page_size' => $pageSize, 'is_generated' => false]);
                GenerateAdmitCardPdfJob::dispatch($existingCard->id);
                $updated++;

                continue;
            }

            $admitCard = AdmitCard::firstOrCreate(
                ['student_id' => $student->id, 'exam_id' => $exam->id],
                ['generated_by' => $generatedBy, 'generated_at' => now(), 'page_size' => $pageSize, 'design' => $design],
            );

            if (! $admitCard->wasRecentlyCreated) {
                $skipped++;

                continue;
            }

            $created++;

            GenerateAdmitCardPdfJob::dispatch($admitCard->id);
        }

        $this->logGeneration($exam, $created, $updated, $skipped, $pageSize, $design);

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'without_routine' => false];
    }

    private function logGeneration(Exam $exam, int $created, int $updated, int $skipped, string $pageSize, AdmitCardDesign $design): void
    {
        $examLabel = $exam->displayLabel();

        activity('admit_card_generation')
            ->performedOn($exam)
            ->event('generated')
            ->withProperties([
                'attributes' => [
                    'exam_id' => $exam->id,
                    'exam_id_label' => $examLabel,
                    'page_size' => $pageSize,
                    'design' => $design->value,
                    'created_count' => $created,
                    'updated_count' => $updated,
                    'skipped_count' => $skipped,
                ],
            ])
            ->log("Generated {$created} admit card(s) for \"{$examLabel}\" — ".($updated > 0 ? "{$updated} switched to the new design, " : '')."{$skipped} skipped (already exists).");
    }
}
