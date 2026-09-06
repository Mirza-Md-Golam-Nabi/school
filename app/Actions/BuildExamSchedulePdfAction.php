<?php

namespace App\Actions;

use App\Models\Exam;
use App\Support\Concerns\BuildsMpdfDocuments;

class BuildExamSchedulePdfAction
{
    use BuildsMpdfDocuments;

    /**
     * Build a PDF listing this exam's subject-wise schedule, sorted by date.
     */
    public function handle(Exam $exam, string $pageSize = 'A4'): string
    {
        $exam->loadMissing(['examType', 'class']);

        $schedules = $exam->schedules()
            ->with('subject')
            ->orderBy('exam_date')
            ->get();

        abort_if($schedules->isEmpty(), 404, 'এই exam-এর জন্য এখনো কোনো schedule সেট করা হয়নি।');

        $mpdf = $this->makeMpdf($pageSize === 'A5' ? 'A5' : 'A4');

        $html = view('documents.exam-schedule', [
            'exam' => $exam,
            'schedules' => $schedules,
        ])->render();

        $mpdf->WriteHTML($html);

        return $this->outputMpdfString($mpdf);
    }
}
