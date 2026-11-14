<?php

namespace App\Actions;

use App\Models\Exam;
use App\Support\Concerns\BuildsMpdfDocuments;

class BuildExamResultReportPdfAction
{
    use BuildsMpdfDocuments;

    public function __construct(
        private readonly BuildExamResultReport $buildReport,
    ) {}

    /**
     * Build this exam's result report (GPA bands, pass/fail, failed-subject
     * counts) as an A4 portrait PDF.
     */
    public function handle(Exam $exam): string
    {
        $report = $this->buildReport->handle($exam);

        abort_if($report['isEmpty'], 404, 'এই exam-এর জন্য এখনো ranking calculate করা হয়নি।');

        $mpdf = $this->makeMpdf('A4', 'P');
        $mpdf->WriteHTML(view('documents.exam-result-report', $report)->render());

        return $this->outputMpdfString($mpdf);
    }
}
