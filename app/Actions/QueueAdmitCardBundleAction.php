<?php

namespace App\Actions;

use App\Jobs\GenerateAdmitCardBundlePdfJob;
use App\Models\AdmitCardBundle;
use App\Models\Exam;
use Illuminate\Support\Str;

class QueueAdmitCardBundleAction
{
    /**
     * Mark the exam's combined admit card PDF not-ready with a fresh token and
     * queue its rebuild — the token stops a job from an older run overwriting it.
     */
    public function handle(Exam $exam): void
    {
        $generationToken = (string) Str::uuid();

        $bundle = AdmitCardBundle::updateOrCreate(
            ['exam_id' => $exam->id],
            ['is_generated' => false, 'generation_token' => $generationToken],
        );

        GenerateAdmitCardBundlePdfJob::dispatch($bundle->id, $generationToken);
    }
}
