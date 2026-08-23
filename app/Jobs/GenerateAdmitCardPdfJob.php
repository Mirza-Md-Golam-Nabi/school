<?php

namespace App\Jobs;

use App\Models\AdmitCard;
use App\Support\Concerns\BuildsMpdfDocuments;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateAdmitCardPdfJob implements ShouldQueue
{
    use BuildsMpdfDocuments;
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

        $mpdf = $this->makeMpdf($admitCard->page_size);
        $mpdf->WriteHTML($html);

        $path = "documents/admit_cards/{$admitCard->exam->session_year}/{$admitCard->exam_id}/{$admitCard->student_id}.pdf";
        Storage::disk('local')->put($path, $this->outputMpdfString($mpdf));

        $admitCard->update([
            'file_path' => $path,
            'file_generated_at' => now(),
            'is_generated' => true,
        ]);
    }
}
