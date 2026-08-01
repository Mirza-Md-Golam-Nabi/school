<?php

namespace App\Actions;

use App\Jobs\GenerateMarksheetPdfJob;
use App\Models\Exam;
use App\Models\Marksheet;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;

class GenerateMarksheetsForExamAction
{
    /**
     * Create a marksheet record for every active student in the exam's class who
     * already has a calculated ranking (skipping students without one, since a
     * marksheet needs rank/GPA to print), skip students who already have one
     * (unique student_id/exam_id), and dispatch a PDF generation job for each
     * newly created record.
     *
     * @return array{created: int, skipped_already_exists: int, skipped_no_ranking: int}
     */
    public function handle(Exam $exam, ?int $generatedBy = null): array
    {
        $created = 0;
        $skippedAlreadyExists = 0;
        $skippedNoRanking = 0;

        $students = StudentProfile::active()
            ->where('current_class_id', $exam->class_id)
            ->get(['id']);

        $rankedStudentIds = StudentMeritRanking::where('exam_id', $exam->id)
            ->pluck('student_id')
            ->all();

        foreach ($students as $student) {
            if (! in_array($student->id, $rankedStudentIds, true)) {
                $skippedNoRanking++;

                continue;
            }

            $marksheet = Marksheet::firstOrCreate(
                ['student_id' => $student->id, 'exam_id' => $exam->id],
                ['generated_by' => $generatedBy, 'generated_at' => now()],
            );

            if (! $marksheet->wasRecentlyCreated) {
                $skippedAlreadyExists++;

                continue;
            }

            $created++;

            GenerateMarksheetPdfJob::dispatch($marksheet->id);
        }

        return [
            'created' => $created,
            'skipped_already_exists' => $skippedAlreadyExists,
            'skipped_no_ranking' => $skippedNoRanking,
        ];
    }
}
