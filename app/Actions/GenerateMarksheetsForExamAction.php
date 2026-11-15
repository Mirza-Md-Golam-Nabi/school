<?php

namespace App\Actions;

use App\Enums\MarksheetBundleType;
use App\Jobs\GenerateMarksheetBundlePdfJob;
use App\Jobs\GenerateMarksheetPdfJob;
use App\Models\Exam;
use App\Models\Marksheet;
use App\Models\MarksheetBundle;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use Illuminate\Support\Str;

class GenerateMarksheetsForExamAction
{
    /**
     * Create a marksheet record for every active student in the exam's class who
     * already has a calculated ranking (skipping students without one, since a
     * marksheet needs rank/GPA to print). Students who already have a marksheet
     * are regenerated — the PDF job deletes the old file and writes a fresh one —
     * rather than skipped, so re-running this always produces up-to-date PDFs.
     *
     * $subjectLanguage controls the generated marksheets' document language
     * (subject names, labels, and numerals) — baked into the stored PDF.
     *
     * @return array{created: int, regenerated: int, skipped_no_ranking: int}
     */
    public function handle(Exam $exam, ?int $generatedBy = null, string $subjectLanguage = 'en'): array
    {
        $created = 0;
        $regenerated = 0;
        $skippedNoRanking = 0;

        $students = StudentProfile::active()
            ->where('current_class_id', $exam->class_id)
            ->get(['id']);

        $rankedStudentIds = StudentMeritRanking::where('exam_id', $exam->id)
            ->pluck('student_id')
            ->flip();

        $existingMarksheets = Marksheet::where('exam_id', $exam->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        foreach ($students as $student) {
            if (! $rankedStudentIds->has($student->id)) {
                $skippedNoRanking++;

                continue;
            }

            $marksheet = $existingMarksheets->get($student->id) ?? Marksheet::firstOrCreate(
                ['student_id' => $student->id, 'exam_id' => $exam->id],
                ['generated_by' => $generatedBy, 'generated_at' => now()],
            );

            if ($marksheet->wasRecentlyCreated) {
                $created++;
            } else {
                $marksheet->update([
                    'generated_by' => $generatedBy,
                    'generated_at' => now(),
                    'is_generated' => false,
                ]);
                $regenerated++;
            }

            GenerateMarksheetPdfJob::dispatch($marksheet->id, $subjectLanguage);
        }

        if ($created + $regenerated > 0) {
            $this->queueBundles($exam, $subjectLanguage);
        }

        $this->logGeneration($exam, $created, $regenerated, $skippedNoRanking);

        return [
            'created' => $created,
            'regenerated' => $regenerated,
            'skipped_no_ranking' => $skippedNoRanking,
        ];
    }

    /**
     * Alongside the per-student PDFs, queue the three ready-made combined PDFs
     * (all / passed / failed students). Each bundle is marked not-ready with a
     * fresh token first, so a job from an older run can't overwrite it.
     */
    private function queueBundles(Exam $exam, string $subjectLanguage): void
    {
        foreach (MarksheetBundleType::cases() as $type) {
            $generationToken = (string) Str::uuid();

            $bundle = MarksheetBundle::updateOrCreate(
                ['exam_id' => $exam->id, 'type' => $type],
                [
                    'subject_language' => $subjectLanguage,
                    'is_generated' => false,
                    'generation_token' => $generationToken,
                ],
            );

            GenerateMarksheetBundlePdfJob::dispatch($bundle->id, $generationToken);
        }
    }

    private function logGeneration(Exam $exam, int $created, int $regenerated, int $skippedNoRanking): void
    {
        $examLabel = $exam->displayLabel();

        activity('marksheet_generation')
            ->performedOn($exam)
            ->event('generated')
            ->withProperties([
                'attributes' => [
                    'exam_id' => $exam->id,
                    'exam_id_label' => $examLabel,
                    'created_count' => $created,
                    'regenerated_count' => $regenerated,
                    'skipped_no_ranking_count' => $skippedNoRanking,
                ],
            ])
            ->log("Generated {$created} marksheet(s) for \"{$examLabel}\" ({$regenerated} regenerated) — {$skippedNoRanking} skipped (no ranking yet).");
    }
}
