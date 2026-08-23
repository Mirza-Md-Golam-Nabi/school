<?php

namespace App\Actions;

use App\Models\Classes;
use App\Models\StudentProfile;
use App\Support\Concerns\BuildsMpdfDocuments;

class BuildClassSeatPlanPdfAction
{
    use BuildsMpdfDocuments;

    /**
     * Build a seat plan PDF listing every active student in this class — name,
     * roll no, class, and group/section where applicable — three per row,
     * across as many A4 pages as needed.
     */
    public function handle(Classes $class): string
    {
        $students = StudentProfile::with(['user', 'class', 'group', 'section'])
            ->active()
            ->where('current_class_id', $class->id)
            ->orderBy('roll_no')
            ->get();

        abort_if($students->isEmpty(), 404, 'এই ক্লাসে এখনো কোনো active student নেই।');

        $mpdf = $this->makeMpdf();

        $html = view('documents.seat-plan', ['students' => $students])->render();

        $mpdf->WriteHTML($html);

        return $this->outputMpdfString($mpdf);
    }
}
