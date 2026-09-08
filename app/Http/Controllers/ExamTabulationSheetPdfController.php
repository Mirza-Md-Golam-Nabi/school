<?php

namespace App\Http\Controllers;

use App\Actions\BuildExamTabulationSheetPdfAction;
use App\Models\Exam;
use App\Support\Concerns\SanitizesFilenames;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExamTabulationSheetPdfController extends Controller
{
    use SanitizesFilenames;

    /**
     * Stream this exam's tabulation sheet PDF as a download.
     */
    public function __invoke(Exam $exam, BuildExamTabulationSheetPdfAction $action, Request $request): Response
    {
        $exam->loadMissing(['examType', 'class']);

        $pageSize = (string) $request->query('pageSize', 'A4');
        $orientation = $request->query('orientation');

        $pdf = $action->handle($exam, $pageSize, is_string($orientation) ? $orientation : null);

        $filename = "tabulation-sheet-{$this->sanitizeFilenameSegment($exam->class?->name ?? 'Class')}-{$this->sanitizeFilenameSegment($exam->examType?->name ?? 'Exam')}-{$exam->session_year}.pdf";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
