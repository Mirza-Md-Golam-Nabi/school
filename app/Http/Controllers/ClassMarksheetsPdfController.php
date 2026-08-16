<?php

namespace App\Http\Controllers;

use App\Actions\BuildCombinedClassMarksheetPdfAction;
use App\Models\Classes;
use App\Models\Exam;
use Illuminate\Http\Response;

class ClassMarksheetsPdfController extends Controller
{
    /**
     * Stream a single PDF combining every generated marksheet for this class + exam,
     * one student per page, as a download.
     */
    public function __invoke(Classes $class, Exam $exam, BuildCombinedClassMarksheetPdfAction $action): Response
    {
        $pdf = $action->handle($class, $exam);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"marksheets-{$class->id}-{$exam->id}.pdf\"",
        ]);
    }
}
