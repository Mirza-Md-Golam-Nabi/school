<?php

namespace App\Actions;

use App\Models\Classes;
use App\Support\Concerns\BuildsMpdfDocuments;

class BuildClassAttendanceReportPdfAction
{
    use BuildsMpdfDocuments;

    public function __construct(
        private readonly BuildClassAttendanceReportRows $buildClassAttendanceReportRows,
    ) {}

    /**
     * Build a monthly attendance report PDF for every active student in this
     * class — roll, name, and a P/A cell for each day of the given month.
     */
    public function handle(Classes $class, int $year, int $month): string
    {
        ['rows' => $rows, 'daysInMonth' => $daysInMonth] = $this->buildClassAttendanceReportRows->handle($class, $year, $month);

        $mpdf = $this->makeMpdf('A4', 'L');

        $html = view('documents.attendance-report', [
            'class' => $class,
            'year' => $year,
            'month' => $month,
            'daysInMonth' => $daysInMonth,
            'rows' => $rows,
        ])->render();

        $mpdf->WriteHTML($html);

        return $this->outputMpdfString($mpdf);
    }
}
