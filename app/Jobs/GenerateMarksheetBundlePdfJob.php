<?php

namespace App\Jobs;

use App\Actions\BuildCombinedClassMarksheetPdfAction;
use App\Models\MarksheetBundle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Builds one ready-made combined marksheet PDF (all / passed / failed students)
 * of an exam and stores it, so the admin's download is instant.
 */
class GenerateMarksheetBundlePdfJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    /**
     * $generationToken ties this job to one "Generate Marksheets" run — if a
     * newer run has started meanwhile, this job's (stale) result is dropped.
     */
    public function __construct(public int $marksheetBundleId, public string $generationToken) {}

    public function handle(BuildCombinedClassMarksheetPdfAction $buildCombinedClassMarksheetPdf): void
    {
        $bundle = MarksheetBundle::with('exam.class')->find($this->marksheetBundleId);

        if (! $bundle || ! $this->isCurrentGeneration($bundle) || ! $bundle->exam?->class) {
            return;
        }

        ['pdf' => $pdf, 'student_count' => $studentCount] = $buildCombinedClassMarksheetPdf
            ->buildBundle($bundle->exam, $bundle->type, $bundle->subject_language);

        if (! $this->isCurrentGeneration($bundle->refresh())) {
            return;
        }

        $disk = Storage::disk('local');
        $path = "documents/marksheets/{$bundle->exam->session_year}/{$bundle->exam_id}/bundle-{$bundle->type->value}.pdf";

        if ($pdf === null) {
            if ($bundle->file_path && $disk->exists($bundle->file_path)) {
                $disk->delete($bundle->file_path);
            }

            $path = null;
        } else {
            $disk->put($path, $pdf);
        }

        $bundle->update([
            'file_path' => $path,
            'student_count' => $studentCount,
            'is_generated' => true,
            'file_generated_at' => now(),
        ]);
    }

    private function isCurrentGeneration(MarksheetBundle $bundle): bool
    {
        return $bundle->generation_token === $this->generationToken;
    }
}
