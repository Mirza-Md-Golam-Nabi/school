<?php

namespace App\Http\Controllers;

use App\Actions\BuildTeacherAttendanceSheetPdfAction;
use App\Http\Requests\DownloadTeacherAttendanceSheetRequest;
use App\Models\TeacherProfile;
use Carbon\Carbon;
use Illuminate\Http\Response;

class TeacherAttendanceSheetPdfController extends Controller
{
    /**
     * Stream the month's attendance sheet of all active teachers as a PDF download.
     */
    public function __invoke(DownloadTeacherAttendanceSheetRequest $request, int $year, int $month, BuildTeacherAttendanceSheetPdfAction $action): Response
    {
        abort_unless(TeacherProfile::query()->active()->exists(), 404, 'এখনো কোনো active teacher নেই।');

        $pdf = $action->handle($year, $month);

        $monthName = Carbon::create($year, $month, 1)->format('F');

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"teacher-attendance-sheet-{$monthName}-{$year}.pdf\"",
        ]);
    }
}
