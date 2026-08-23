<?php

namespace App\Http\Controllers;

use App\Actions\BuildClassSeatPlanPdfAction;
use App\Models\Classes;
use Illuminate\Http\Response;

class ClassSeatPlanPdfController extends Controller
{
    /**
     * Stream a seat plan PDF listing every active student in this class,
     * three per row, as a download.
     */
    public function __invoke(Classes $class, BuildClassSeatPlanPdfAction $action): Response
    {
        $pdf = $action->handle($class);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"seat-plan-{$class->id}.pdf\"",
        ]);
    }
}
