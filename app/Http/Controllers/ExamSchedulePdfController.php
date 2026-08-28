<?php

namespace App\Http\Controllers;

use App\Actions\BuildExamSchedulePdfAction;
use App\Models\Exam;
use App\Support\Concerns\SanitizesFilenames;
use Illuminate\Http\Response;

class ExamSchedulePdfController extends Controller
{
    use SanitizesFilenames;

    /**
     * Stream this exam's subject-wise schedule PDF as a download.
     */
    public function __invoke(Exam $exam, BuildExamSchedulePdfAction $action): Response
    {
        $exam->loadMissing(['examType', 'class']);

        $pdf = $action->handle($exam);

        $filename = "exam-schedule-{$this->sanitizeFilenameSegment($exam->class?->name ?? 'Class')}-{$this->sanitizeFilenameSegment($exam->examType?->name ?? 'Exam')}-{$exam->session_year}.pdf";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
