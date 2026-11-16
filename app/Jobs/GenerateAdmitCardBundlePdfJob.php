<?php

namespace App\Jobs;

use App\Actions\BuildCombinedClassAdmitCardsPdfAction;
use App\Models\AdmitCardBundle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Builds one ready-made combined admit card PDF of an exam's class and
 * stores it, so the admin's "Download All" is instant.
 */
class GenerateAdmitCardBundlePdfJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    /**
     * $generationToken ties this job to one generation run — if a newer run
     * has started meanwhile, this job's (stale) result is dropped.
     */
    public function __construct(public int $admitCardBundleId, public string $generationToken) {}

    public function handle(BuildCombinedClassAdmitCardsPdfAction $buildCombinedClassAdmitCardsPdf): void
    {
        $bundle = AdmitCardBundle::with('exam')->find($this->admitCardBundleId);

        if (! $bundle || ! $this->isCurrentGeneration($bundle) || ! $bundle->exam) {
            return;
        }

        ['pdf' => $pdf, 'card_count' => $cardCount] = $buildCombinedClassAdmitCardsPdf->buildBundle($bundle->exam);

        if (! $this->isCurrentGeneration($bundle->refresh())) {
            return;
        }

        $path = null;

        if ($pdf === null) {
            $bundle->deleteStoredFile();
        } else {
            $path = "documents/admit_cards/{$bundle->exam->session_year}/{$bundle->exam_id}/bundle.pdf";
            Storage::disk('local')->put($path, $pdf);
        }

        $bundle->update([
            'file_path' => $path,
            'card_count' => $cardCount,
            'is_generated' => true,
            'file_generated_at' => now(),
        ]);
    }

    private function isCurrentGeneration(AdmitCardBundle $bundle): bool
    {
        return $bundle->generation_token === $this->generationToken;
    }
}
