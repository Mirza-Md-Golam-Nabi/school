<?php

namespace App\Http\Controllers;

use App\Actions\BuildSubjectListPdfAction;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SubjectListPdfController extends Controller
{
    /**
     * Stream a PDF listing every subject as a download — only for admin-panel users.
     */
    public function __invoke(BuildSubjectListPdfAction $action, Request $request): Response
    {
        abort_unless($request->user()?->canAccessPanel(Filament::getPanel('admin')), 403);

        $columns = (array) $request->query('columns', []);
        $orientation = (string) $request->query('orientation', 'P');

        $pdf = $action->handle($columns, $orientation);

        $filename = 'subject-list-'.now()->format('Y-m-d').'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
