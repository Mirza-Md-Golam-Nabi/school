<?php

namespace App\Http\Controllers;

use App\Actions\BuildEmployeeAttendanceReportPdfAction;
use App\Http\Requests\DownloadEmployeeAttendanceReportRequest;
use App\Support\Concerns\SanitizesFilenames;
use Carbon\Carbon;
use Illuminate\Http\Response;

class EmployeeAttendanceReportPdfController extends Controller
{
    use SanitizesFilenames;

    /**
     * Stream a monthly attendance report PDF for the selected teachers and staff as a download.
     */
    public function __invoke(DownloadEmployeeAttendanceReportRequest $request, int $year, int $month, BuildEmployeeAttendanceReportPdfAction $action): Response
    {
        $people = $request->people();

        abort_if($people->isEmpty(), 404, 'নির্বাচিত teacher বা staff খুঁজে পাওয়া যায়নি।');

        $pdf = $action->handle($people, $year, $month);

        $monthName = Carbon::create($year, $month, 1)->format('F');
        $subject = $people->count() === 1
            ? $this->sanitizeFilenameSegment((string) $people->first()->user?->name)
            : 'teachers-staff';
        $filename = "attendance-report-{$subject}-{$monthName}-{$year}.pdf";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
