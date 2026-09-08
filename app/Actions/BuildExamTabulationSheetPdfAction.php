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

    private const PAGE_SIZES = ['A4', 'A3', 'Legal'];

    private const ORIENTATIONS = ['P', 'L'];

    /**
     * Build this exam's tabulation sheet PDF — one flat table for Primary/Class
     * 6-8, or one table per group for Class 9-10 — from the already-calculated
     * StudentMeritRanking rows.
     *
     * $pageSize/$orientation come from the user's download-modal choice; an
     * unrecognized/omitted $orientation falls back to the layout's own
     * auto-detected orientation (portrait for Primary, landscape otherwise).
     */
    public function handle(Exam $exam, string $pageSize = 'A4', ?string $orientation = null): string
    {
        $detail = $this->buildDetail->handle($exam);

        abort_if($detail['isEmpty'], 404, 'এই exam-এর জন্য এখনো ranking calculate করা হয়নি।');

        $resolvedPageSize = in_array($pageSize, self::PAGE_SIZES, true) ? $pageSize : 'A4';
        $resolvedOrientation = in_array($orientation, self::ORIENTATIONS, true) ? $orientation : $detail['orientation'];

        $mpdf = $this->makeMpdf($resolvedPageSize, $resolvedOrientation);

        $html = view($detail['view'], $detail)->render();

        $mpdf->WriteHTML($html);

        return $this->outputMpdfString($mpdf);
    }
}
