<?php

namespace App\Actions;

use App\Support\Concerns\BuildsMpdfDocuments;

class BuildTeacherAttendanceSheetPdfAction
{
    use BuildsMpdfDocuments;

    public function __construct(
        private readonly BuildTeacherAttendanceSheetRows $buildTeacherAttendanceSheetRows,
    ) {}

    /**
     * Build one landscape PDF with every active teacher down the left and the
     * month's dates across the top, each cell marked P / LP / A / L.
     */
    public function handle(int $year, int $month): string
    {
        ['rows' => $rows, 'days' => $days] = $this->buildTeacherAttendanceSheetRows->handle($year, $month);

        $mpdf = $this->makeMpdf('A4', 'L');

        $mpdf->WriteHTML(view('documents.teacher-attendance-sheet', [
            'rows' => $rows,
            'days' => $days,
            'year' => $year,
            'month' => $month,
        ])->render());

        return $this->outputMpdfString($mpdf);
    }
}
