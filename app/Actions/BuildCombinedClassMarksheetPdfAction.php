<?php

namespace App\Actions;

use App\Models\Classes;
use App\Models\Exam;
use App\Models\Marksheet;
use App\Models\StudentMeritRanking;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class BuildCombinedClassMarksheetPdfAction
{
    /**
     * Combine every already-generated marksheet for this class + exam into one
     * PDF, one student per page, reusing the same per-student layout and data
     * as the individual marksheet PDFs.
     */
    public function handle(Classes $class, Exam $exam): string
    {
        $marksheets = Marksheet::where('exam_id', $exam->id)
            ->where('is_generated', true)
            ->whereHas('student', fn ($query) => $query->where('current_class_id', $class->id))
            ->with(['student.user', 'student.class', 'student.section', 'exam.examType'])
            ->get()
            ->sortBy(fn (Marksheet $marksheet) => $marksheet->student?->roll_no)
            ->values();

        abort_if($marksheets->isEmpty(), 404, 'এই ক্লাস ও exam-এর জন্য এখনো কোনো marksheet generate করা হয়নি।');

        $fontPath = public_path('fonts');

        $mpdf = new Mpdf([
            'format' => 'A4',
            'orientation' => 'P',
            'fontDir' => array_merge((new ConfigVariables)->getDefaults()['fontDir'], [$fontPath]),
            'fontdata' => (new FontVariables)->getDefaults()['fontdata'] + [
                'solaimanlipi' => ['R' => 'SolaimanLipi.ttf', 'useOTL' => 0xFF],
            ],
            'default_font' => 'solaimanlipi',
            'tempDir' => storage_path('app/mpdf'),
        ]);

        foreach ($marksheets as $index => $marksheet) {
            $ranking = StudentMeritRanking::where('exam_id', $marksheet->exam_id)
                ->where('student_id', $marksheet->student_id)
                ->first();

            if (! $ranking) {
                continue;
            }

            ['rows' => $rows, 'summary' => $summary] = app(BuildStudentMarksDetail::class)->handle($ranking);

            $html = view('documents.marksheet', [
                'marksheet' => $marksheet,
                'rows' => $rows,
                'summary' => $summary,
            ])->render();

            if ($index > 0) {
                $mpdf->AddPage();
            }

            $mpdf->WriteHTML($html);
        }

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
