<?php

namespace App\Actions;

use App\Jobs\GenerateAdmitCardPdfJob;
use App\Models\AdmitCard;
use App\Models\Exam;
use App\Models\StudentProfile;

class GenerateAdmitCardsForExamAction
{
    /**
     * Create an admit card record for every active student in the exam's class,
     * skipping students who already have one (unique student_id/exam_id), and
     * dispatch a PDF generation job for each newly created record.
     *
     * @return array{created: int, skipped: int}
     */
    public function handle(Exam $exam, ?int $generatedBy = null, string $pageSize = 'A4'): array
    {
        $created = 0;
        $skipped = 0;

        $students = StudentProfile::active()
            ->where('current_class_id', $exam->class_id)
            ->get(['id']);

        foreach ($students as $student) {
            $admitCard = AdmitCard::firstOrCreate(
                ['student_id' => $student->id, 'exam_id' => $exam->id],
                ['generated_by' => $generatedBy, 'generated_at' => now(), 'page_size' => $pageSize],
            );

            if (! $admitCard->wasRecentlyCreated) {
                $skipped++;

                continue;
            }

            $created++;

            GenerateAdmitCardPdfJob::dispatch($admitCard->id);
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
