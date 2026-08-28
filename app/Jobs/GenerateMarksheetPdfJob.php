<?php

namespace App\Jobs;

use App\Actions\BuildStudentMarksDetail;
use App\Models\Marksheet;
use App\Models\StudentMeritRanking;
use App\Support\Concerns\BuildsMpdfDocuments;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateMarksheetPdfJob implements ShouldQueue
{
    use BuildsMpdfDocuments;
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

        $mpdf = $this->makeMpdf();
        $mpdf->WriteHTML($html);

        $path = "documents/marksheets/{$marksheet->exam->session_year}/{$marksheet->exam_id}/{$marksheet->student_id}.pdf";
        Storage::disk('local')->put($path, $this->outputMpdfString($mpdf));

        $marksheet->update([
            'file_path' => $path,
            'file_generated_at' => now(),
            'is_generated' => true,
        ]);
    }
}
