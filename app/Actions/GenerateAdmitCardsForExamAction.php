<?php

namespace App\Actions;

use App\Enums\AdmitCardDesign;
use App\Jobs\GenerateAdmitCardPdfJob;
use App\Models\AdmitCard;
use App\Models\AdmitCardBundle;
use App\Models\Exam;
use App\Models\StudentProfile;
use App\Support\DocumentLocale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class GenerateAdmitCardsForExamAction
{
    /**
     * Create an admit card record for every active student in the exam's class
     * and dispatch a PDF generation job for each. A student who already has a
     * card for this exam (unique student_id/exam_id) is skipped — unless the
     * card is of another design or language, in which case it is switched to
     * this one and generated again.
     *
     * Each student keeps only one admit card: the class's cards of every
     * other exam are deleted (files included) before generating.
     *
     * $routineLanguage and $studentInfoLanguage ('en' / 'bn') set the language
     * of the card's exam routine and of its student details, each on its own.
     *
     * A card with the routine is never generated for an exam without an Exam
     * Schedule: nothing is created, and `without_routine` says so.
     *
     * Alongside the per-card jobs, the class's combined "Download All" PDF is
     * queued too, whenever a card changed or the stored one is missing/stale.
     *
     * @return array{created: int, updated: int, skipped: int, without_routine: bool}
     */
    public function handle(
        Exam $exam,
        ?int $generatedBy = null,
        string $pageSize = 'A4',
        AdmitCardDesign $design = AdmitCardDesign::Simple,
        string $routineLanguage = 'en',
        string $studentInfoLanguage = 'en',
    ): array {
        if ($design === AdmitCardDesign::WithRoutine && ! $exam->schedules()->exists()) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'without_routine' => true];
        }

        $languages = [
            'routine_language' => DocumentLocale::normalize($routineLanguage),
            'student_info_language' => DocumentLocale::normalize($studentInfoLanguage),
        ];

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $students = StudentProfile::active()
            ->where('current_class_id', $exam->class_id)
            ->get(['id']);

        $this->clearAdmitCardsFromOtherExams($exam);

        $existingCards = AdmitCard::where('exam_id', $exam->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->get(['id', 'student_id', 'design', 'routine_language', 'student_info_language'])
            ->keyBy('student_id');

        foreach ($students as $student) {
            $existingCard = $existingCards->get($student->id);

            if ($existingCard && $this->matches($existingCard, $design, $languages)) {
                $skipped++;

                continue;
            }

            if ($existingCard) {
                $existingCard->update(['design' => $design, 'page_size' => $pageSize, 'is_generated' => false, ...$languages]);
                GenerateAdmitCardPdfJob::dispatch($existingCard->id);
                $updated++;

                continue;
            }

            $admitCard = AdmitCard::firstOrCreate(
                ['student_id' => $student->id, 'exam_id' => $exam->id],
                ['generated_by' => $generatedBy, 'generated_at' => now(), 'page_size' => $pageSize, 'design' => $design, ...$languages],
            );

            if (! $admitCard->wasRecentlyCreated) {
                $skipped++;

                continue;
            }

            $created++;

            GenerateAdmitCardPdfJob::dispatch($admitCard->id);
        }

        if ($created + $updated > 0 || ! $this->hasReadyBundle($exam)) {
            app(QueueAdmitCardBundleAction::class)->handle($exam);
        }

        $this->logGeneration($exam, $created, $updated, $skipped, $pageSize, $design, $languages);

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'without_routine' => false];
    }

    /**
     * A class keeps only one admit card per student: before generating this
     * exam's cards, every card (and combined PDF) of any other exam — either
     * held by the class or belonging to a student now in the class — is
     * deleted along with its stored file.
     */
    private function clearAdmitCardsFromOtherExams(Exam $exam): void
    {
        $otherExamIdsOfClass = Exam::where('class_id', $exam->class_id)
            ->whereKeyNot($exam->id)
            ->select('id');

        AdmitCardBundle::whereIn('exam_id', $otherExamIdsOfClass)
            ->get()
            ->each(function (AdmitCardBundle $bundle): void {
                $bundle->deleteStoredFile();
                $bundle->delete();
            });

        $clearedCount = 0;

        AdmitCard::where('exam_id', '!=', $exam->id)
            ->where(fn (Builder $query) => $query
                ->whereIn('exam_id', $otherExamIdsOfClass)
                ->orWhereHas('student', fn (Builder $studentQuery) => $studentQuery->where('current_class_id', $exam->class_id)))
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

        activity('admit_card_generation')
            ->performedOn($exam)
            ->event('deleted')
            ->withProperties([
                'attributes' => [
                    'exam_id' => $exam->id,
                    'exam_id_label' => $exam->displayLabel(),
                    'cleared_count' => $clearedCount,
                ],
            ])
            ->log("Cleared {$clearedCount} old admit card(s) of other exams before generating \"{$exam->displayLabel()}\" admit cards.");
    }

    /**
     * Whether an existing card already has this design and these languages.
     *
     * @param  array{routine_language: string, student_info_language: string}  $languages
     */
    private function matches(AdmitCard $admitCard, AdmitCardDesign $design, array $languages): bool
    {
        return $admitCard->design === $design
            && $admitCard->routine_language === $languages['routine_language']
            && $admitCard->student_info_language === $languages['student_info_language'];
    }

    /**
     * Whether the stored combined PDF still covers exactly this exam's cards —
     * if so, a run that changed nothing need not rebuild it.
     */
    private function hasReadyBundle(Exam $exam): bool
    {
        $bundle = AdmitCardBundle::where('exam_id', $exam->id)->first();

        if (! $bundle) {
            return false;
        }

        $currentCardCount = app(BuildCombinedClassAdmitCardsPdfAction::class)
            ->admitCardsQuery($exam->class_id, $exam)
            ->count();

        return $currentCardCount === 0 || $bundle->isReadyFor($currentCardCount);
    }

    /**
     * @param  array{routine_language: string, student_info_language: string}  $languages
     */
    private function logGeneration(Exam $exam, int $created, int $updated, int $skipped, string $pageSize, AdmitCardDesign $design, array $languages): void
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
                    ...$languages,
                    'created_count' => $created,
                    'updated_count' => $updated,
                    'skipped_count' => $skipped,
                ],
            ])
            ->log("Generated {$created} admit card(s) for \"{$examLabel}\" — ".($updated > 0 ? "{$updated} switched to the new design/language, " : '')."{$skipped} skipped (already exists).");
    }
}
