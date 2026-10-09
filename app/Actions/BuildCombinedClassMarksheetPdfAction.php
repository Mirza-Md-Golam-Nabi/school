<?php

namespace App\Actions;

use App\Models\Classes;
use App\Models\Exam;
use App\Models\Marksheet;
use App\Models\StudentMeritRanking;
use App\Support\Concerns\BuildsMpdfDocuments;
use Illuminate\Support\Facades\App;

class BuildCombinedClassMarksheetPdfAction
{
    use BuildsMpdfDocuments;

    /**
     * Combine every already-generated marksheet for this class + exam into one
     * PDF, one student per page, reusing the same per-student layout and data
     * as the individual marksheet PDFs.
     *
     * $subjectLanguage controls only this combined PDF's document language —
     * independent of whatever language each marksheet was originally generated in.
     */
    public function handle(Classes $class, Exam $exam, string $subjectLanguage = 'en'): string
    {
        $marksheets = Marksheet::where('exam_id', $exam->id)
            ->where('is_generated', true)
            ->whereHas('student', fn ($query) => $query->where('current_class_id', $class->id))
            ->with(['student.user', 'student.class', 'student.section', 'exam.examType', 'exam.class'])
            ->get()
            ->sortBy(fn (Marksheet $marksheet) => $marksheet->student?->roll_no)
            ->values();

        abort_if($marksheets->isEmpty(), 404, 'এই ক্লাস ও exam-এর জন্য এখনো কোনো marksheet generate করা হয়নি।');

        $mpdf = $this->makeMpdf();

        $currentLocale = App::getLocale();
        App::setLocale(array_key_exists($subjectLanguage, config('app.available_locales')) ? $subjectLanguage : 'en');

        // Loaded once for the whole class — the loop below used to query the
        // ranking, the exam-wide results and the section check per student.
        $rankings = StudentMeritRanking::where('exam_id', $exam->id)
            ->whereIn('student_id', $marksheets->pluck('student_id'))
            ->get()
            ->keyBy('student_id');

        $hasSections = $marksheets->first()->exam?->class?->sections()->exists() ?? false;
        $buildStudentMarksDetail = app(BuildStudentMarksDetail::class);

        try {
            foreach ($marksheets as $index => $marksheet) {
                $ranking = $rankings->get($marksheet->student_id);

                if (! $ranking) {
                    continue;
                }

                $ranking->setRelation('student', $marksheet->student);
                $ranking->setRelation('exam', $marksheet->exam);

                ['rows' => $rows, 'summary' => $summary] = $buildStudentMarksDetail->handle($ranking);

                $html = view('documents.marksheet', [
                    'marksheet' => $marksheet,
                    'rows' => $rows,
                    'summary' => $summary,
                    'hasSections' => $hasSections,
                ])->render();

                if ($index > 0) {
                    $mpdf->AddPage();
                }

                $mpdf->WriteHTML($html);
            }
        } finally {
            App::setLocale($currentLocale);
        }

        return $this->outputMpdfString($mpdf);
    }
}
