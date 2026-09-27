<?php

namespace App\Actions;

use App\Models\Exam;
use App\Support\Concerns\BuildsMpdfDocuments;
use Illuminate\Support\Facades\App;

class BuildExamSchedulePdfAction
{
    use BuildsMpdfDocuments;

    /**
     * Build a PDF listing this exam's subject-wise schedule, sorted by date.
     *
     * $subjectLanguage controls only the subject names printed on the PDF
     * (via Subject::display_name) — independent of the admin's own UI locale.
     */
    public function handle(Exam $exam, string $pageSize = 'A4', string $subjectLanguage = 'en'): string
    {
        $exam->loadMissing(['examType', 'class']);

        $schedules = $exam->schedules()
            ->with('subject')
            ->orderBy('exam_date')
            ->get();

        abort_if($schedules->isEmpty(), 404, 'এই exam-এর জন্য এখনো কোনো schedule সেট করা হয়নি।');

        $mpdf = $this->makeMpdf($pageSize === 'A5' ? 'A5' : 'A4');

        $currentLocale = App::getLocale();
        App::setLocale(array_key_exists($subjectLanguage, config('app.available_locales')) ? $subjectLanguage : 'en');

        try {
            $html = view('documents.exam-schedule', [
                'exam' => $exam,
                'schedules' => $schedules,
            ])->render();
        } finally {
            App::setLocale($currentLocale);
        }

        $mpdf->WriteHTML($html);

        return $this->outputMpdfString($mpdf);
    }
}
