<?php

namespace App\Http\Controllers;

use App\Actions\BuildCombinedClassMarksheetPdfAction;
use App\Models\Classes;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ClassMarksheetsPdfController extends Controller
{
    /**
     * Stream a single PDF combining every generated marksheet for this class + exam,
     * one student per page, as a download. ?result=passed|failed narrows it
     * to students who passed every subject, or to students who failed.
     */
    public function __invoke(Classes $class, Exam $exam, BuildCombinedClassMarksheetPdfAction $action, Request $request): Response
    {
        $subjectLanguage = (string) $request->query('subjectLanguage', 'en');

        $resultFilter = in_array($request->query('result'), [BuildCombinedClassMarksheetPdfAction::RESULT_PASSED, BuildCombinedClassMarksheetPdfAction::RESULT_FAILED], true)
            ? (string) $request->query('result')
            : null;

        $pdf = $action->handle($class, $exam, $subjectLanguage, $resultFilter);

        $fileSuffix = $resultFilter ? "-{$resultFilter}" : '';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"marksheets-{$class->id}-{$exam->id}{$fileSuffix}.pdf\"",
        ]);
    }
}
