<?php

namespace App\Http\Controllers;

use App\Actions\BuildClassAttendanceReportPdfAction;
use App\Models\Classes;
use App\Support\Concerns\SanitizesFilenames;
use Carbon\Carbon;
use Illuminate\Http\Response;

class ClassAttendanceReportPdfController extends Controller
{
    use SanitizesFilenames;

    /**
     * Stream a monthly student attendance report PDF for this class as a download.
     */
    public function __invoke(Classes $class, int $year, int $month, BuildClassAttendanceReportPdfAction $action): Response
    {
        $pdf = $action->handle($class, $year, $month);

        $monthName = Carbon::create($year, $month, 1)->format('F');
        $filename = "attendance-report-{$this->sanitizeFilenameSegment($class->name)}-{$monthName}-{$year}.pdf";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
