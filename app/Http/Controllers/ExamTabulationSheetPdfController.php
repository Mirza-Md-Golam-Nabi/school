<?php

namespace App\Http\Controllers;

use App\Actions\BuildExamTabulationSheetPdfAction;
use App\Models\Exam;
use App\Support\Concerns\SanitizesFilenames;
use Illuminate\Http\Response;

class ExamTabulationSheetPdfController extends Controller
{
    use SanitizesFilenames;

    /**
     * Stream this exam's tabulation sheet PDF as a download.
     */
    public function __invoke(Exam $exam, BuildExamTabulationSheetPdfAction $action): Response
    {
        $exam->loadMissing(['examType', 'class']);

        $pdf = $action->handle($exam);

        $filename = "tabulation-sheet-{$this->sanitizeFilenameSegment($exam->class?->name ?? 'Class')}-{$this->sanitizeFilenameSegment($exam->examType?->name ?? 'Exam')}-{$exam->session_year}.pdf";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
