<?php

namespace App\Jobs;

use App\Models\AdmitCard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class GenerateAdmitCardPdfJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $admitCardId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $admitCard = AdmitCard::with(['student.user', 'student.class', 'student.section', 'exam.examType'])
            ->findOrFail($this->admitCardId);

        if ($admitCard->file_path && Storage::disk('local')->exists($admitCard->file_path)) {
            Storage::disk('local')->delete($admitCard->file_path);
        }

        $html = view('documents.admit-card', ['admitCard' => $admitCard])->render();

        $fontPath = public_path('fonts');

        $mpdf = new Mpdf([
            'format' => $admitCard->page_size,
            'orientation' => 'P',
            'fontDir' => array_merge((new ConfigVariables)->getDefaults()['fontDir'], [$fontPath]),
            'fontdata' => (new FontVariables)->getDefaults()['fontdata'] + [
                'solaimanlipi' => ['R' => 'SolaimanLipi.ttf', 'useOTL' => 0xFF],
            ],
            'default_font' => 'solaimanlipi',
            'tempDir' => storage_path('app/mpdf'),
        ]);
        $mpdf->WriteHTML($html);

        $path = "documents/admit_cards/{$admitCard->exam->session_year}/{$admitCard->exam_id}/{$admitCard->student_id}.pdf";
        Storage::disk('local')->put($path, $mpdf->Output('', Destination::STRING_RETURN));

        $admitCard->update([
            'file_path' => $path,
            'file_generated_at' => now(),
            'is_generated' => true,
        ]);
    }
}
