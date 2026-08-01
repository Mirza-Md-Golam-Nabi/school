<?php

namespace App\Jobs;

use App\Actions\BuildStudentMarksDetail;
use App\Models\Marksheet;
use App\Models\StudentMeritRanking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class GenerateMarksheetPdfJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $marksheetId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $marksheet = Marksheet::with(['student.user', 'student.class', 'student.section', 'exam.examType'])
            ->findOrFail($this->marksheetId);

        $ranking = StudentMeritRanking::where('exam_id', $marksheet->exam_id)
            ->where('student_id', $marksheet->student_id)
            ->firstOrFail();

        ['rows' => $rows, 'summary' => $summary] = app(BuildStudentMarksDetail::class)->handle($ranking);

        if ($marksheet->file_path && Storage::disk('local')->exists($marksheet->file_path)) {
            Storage::disk('local')->delete($marksheet->file_path);
        }

        $html = view('documents.marksheet', [
            'marksheet' => $marksheet,
            'rows' => $rows,
            'summary' => $summary,
        ])->render();

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
        $mpdf->WriteHTML($html);

        $path = "documents/marksheets/{$marksheet->exam->session_year}/{$marksheet->exam_id}/{$marksheet->student_id}.pdf";
        Storage::disk('local')->put($path, $mpdf->Output('', Destination::STRING_RETURN));

        $marksheet->update([
            'file_path' => $path,
            'file_generated_at' => now(),
            'is_generated' => true,
        ]);
    }
}
