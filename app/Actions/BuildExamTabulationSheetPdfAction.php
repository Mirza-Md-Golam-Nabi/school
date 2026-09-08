<?php

namespace App\Actions;

use App\Models\Exam;
use App\Support\Concerns\BuildsMpdfDocuments;

class BuildExamTabulationSheetPdfAction
{
    use BuildsMpdfDocuments;

    public function __construct(
        private readonly BuildExamTabulationSheetDetail $buildDetail,
    ) {}

    /**
     * Build this exam's tabulation sheet PDF — one flat table for Primary/Class
     * 6-8, or one table per group for Class 9-10 — from the already-calculated
     * StudentMeritRanking rows.
     */
    public function handle(Exam $exam): string
    {
        $detail = $this->buildDetail->handle($exam);

        abort_if($detail['isEmpty'], 404, 'এই exam-এর জন্য এখনো ranking calculate করা হয়নি।');

        $mpdf = $this->makeMpdf('A4', $detail['orientation']);

        $html = view($detail['view'], $detail)->render();

        $mpdf->WriteHTML($html);

        return $this->outputMpdfString($mpdf);
    }
}
