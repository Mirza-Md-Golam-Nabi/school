<?php

namespace App\Http\Controllers;

use App\Actions\RenderAdmitCardPdfAction;
use App\Enums\AdmitCardDesign;
use App\Support\AdmitCardSample;
use Illuminate\Http\Response;

class AdmitCardSamplePdfController extends Controller
{
    /**
     * A sample of the design, with made-up student and routine, shown inline
     * in the Generate Admit Cards window so the admin can compare designs.
     */
    public function __invoke(AdmitCardDesign $design, RenderAdmitCardPdfAction $render): Response
    {
        return response($render->handle(AdmitCardSample::make($design)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"admit-card-sample-{$design->value}.pdf\"",
        ]);
    }
}
