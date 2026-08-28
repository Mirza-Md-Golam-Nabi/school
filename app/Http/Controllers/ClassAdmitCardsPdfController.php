<?php

namespace App\Http\Controllers;

use App\Actions\BuildCombinedClassAdmitCardsPdfAction;
use App\Models\Classes;
use App\Models\Exam;
use Illuminate\Http\Response;

class ClassAdmitCardsPdfController extends Controller
{
    /**
     * Stream a single PDF combining every generated admit card for this class + exam,
     * two per row, several rows per A4 page, as a download.
     */
    public function __invoke(Classes $class, Exam $exam, BuildCombinedClassAdmitCardsPdfAction $action): Response
    {
        $pdf = $action->handle($class, $exam);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"admit-cards-{$class->id}-{$exam->id}.pdf\"",
        ]);
    }
}
