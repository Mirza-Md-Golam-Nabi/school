<?php

namespace App\Actions;

use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use App\Support\Concerns\BuildsMpdfDocuments;
use Illuminate\Support\Collection;

class BuildEmployeeAttendanceReportPdfAction
{
    use BuildsMpdfDocuments;

    public function __construct(
        private readonly BuildEmployeeAttendanceReportData $buildEmployeeAttendanceReportData,
    ) {}

    /**
     * Build one monthly attendance report PDF for the given teachers and staff —
     * each person on their own page: profile, month totals, and a line per day.
     *
     * @param  Collection<int, TeacherProfile|StaffProfile>  $people
     */
    public function handle(Collection $people, int $year, int $month): string
    {
        $reports = $people
            ->map(fn (TeacherProfile|StaffProfile $person): array => $this->buildEmployeeAttendanceReportData->handle($person, $year, $month))
            ->values();

        $mpdf = $this->makeMpdf('A4', 'P');

        $mpdf->WriteHTML(view('documents.employee-attendance-report', [
            'reports' => $reports,
            'year' => $year,
            'month' => $month,
        ])->render());

        return $this->outputMpdfString($mpdf);
    }
}
