<?php

namespace App\Http\Controllers;

use App\Actions\BuildClassSeatPlanPdfAction;
use App\Models\Classes;
use App\Support\DocumentLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ClassSeatPlanPdfController extends Controller
{
    /**
     * Stream a seat plan PDF listing every active student in this class,
     * three per row, as a download — in English, or বাংলা with ?language=bn.
     */
    public function __invoke(Request $request, Classes $class, BuildClassSeatPlanPdfAction $action): Response
    {
        $requestedLanguage = $request->query('language');
        $language = DocumentLocale::normalize(is_string($requestedLanguage) ? $requestedLanguage : null);

        $pdf = $action->handle($class, $language);

        $filename = $language === 'en' ? "seat-plan-{$class->id}.pdf" : "seat-plan-{$class->id}-{$language}.pdf";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
