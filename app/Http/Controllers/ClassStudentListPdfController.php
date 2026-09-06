<?php

namespace App\Http\Controllers;

use App\Actions\BuildClassStudentListPdfAction;
use App\Models\Classes;
use App\Support\Concerns\SanitizesFilenames;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ClassStudentListPdfController extends Controller
{
    use SanitizesFilenames;

    /**
     * Stream a PDF listing every student currently enrolled in this class as a download.
     */
    public function __invoke(Classes $class, BuildClassStudentListPdfAction $action, Request $request): Response
    {
        $sessionYear = now()->year;

        $columns = (array) $request->query('columns', []);
        $orientation = (string) $request->query('orientation', 'P');

        $pdf = $action->handle($class, $sessionYear, $columns, $orientation);

        $filename = "student-list-{$this->sanitizeFilenameSegment($class->name)}-{$sessionYear}.pdf";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
