<?php

namespace App\Http\Controllers;

use App\Actions\BuildClassAttendanceReportPdfAction;
use App\Models\Classes;
use Carbon\Carbon;
use Illuminate\Http\Response;

class ClassAttendanceReportPdfController extends Controller
{
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

    /**
     * Strip filesystem-unsafe punctuation and turn whitespace into hyphens,
     * without transliterating — class names may be in Bangla script, which
     * Str::slug() would strip entirely since it only preserves ASCII.
     */
    private function sanitizeFilenameSegment(string $value): string
    {
        $value = preg_replace('/[\/\\\\:*?"<>|]+/u', '', trim($value)) ?? $value;

        return preg_replace('/\s+/u', '-', $value) ?? $value;
    }
}
