<?php

namespace App\Http\Controllers;

use App\Actions\BuildClassStudentIdCardsPdfAction;
use App\Models\Classes;
use Illuminate\Http\Response;

class ClassStudentIdCardsPdfController extends Controller
{
    /**
     * Stream the class's ID cards PDF inline so it opens in the browser for viewing/printing.
     */
    public function view(Classes $class, BuildClassStudentIdCardsPdfAction $action): Response
    {
        return $this->pdfResponse($class, $action, 'inline');
    }

    /**
     * Stream the class's ID cards PDF as a download.
     */
    public function download(Classes $class, BuildClassStudentIdCardsPdfAction $action): Response
    {
        return $this->pdfResponse($class, $action, 'attachment');
    }

    /**
     * Build the PDF holding the ID card (front and back) of every active
     * student in this class, served with the given content disposition.
     */
    private function pdfResponse(Classes $class, BuildClassStudentIdCardsPdfAction $action, string $disposition): Response
    {
        return response($action->handle($class), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"student-id-cards-{$class->id}.pdf\"",
        ]);
    }
}
