<?php

namespace App\Actions;

use App\Models\AdmitCard;
use App\Models\AdmitCardBundle;
use App\Models\Exam;
use App\Models\Marksheet;
use App\Models\MarksheetBundle;

/**
 * Marks the PDFs already made for an exam (marksheets, admit cards and their
 * combined class PDFs) as not generated, e.g. after a subject's name for the
 * exam changed — so the old PDF is not downloaded with the old name. A
 * combined PDF is then built afresh when downloaded; the others are made
 * again with Generate. A bundle's token is cleared too, so a job still
 * running from before cannot mark it ready again.
 */
class MarkExamDocumentsOutdatedAction
{
    /**
     * @return int how many PDFs were marked
     */
    public function handle(Exam $exam): int
    {
        $marked = 0;

        foreach ([Marksheet::class, AdmitCard::class] as $document) {
            $marked += $document::query()
                ->where('exam_id', $exam->id)
                ->where('is_generated', true)
                ->update(['is_generated' => false]);
        }

        foreach ([MarksheetBundle::class, AdmitCardBundle::class] as $bundle) {
            $marked += $bundle::query()
                ->where('exam_id', $exam->id)
                ->where('is_generated', true)
                ->update(['is_generated' => false, 'generation_token' => null]);
        }

        return $marked;
    }
}
