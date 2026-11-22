<?php

namespace App\Http\Controllers;

use App\Actions\BuildTeacherListPdfAction;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TeacherListPdfController extends Controller
{
    /**
     * Stream a PDF listing every teacher as a download — only for admin-panel users,
     * since it can include teachers' phone numbers and addresses.
     */
    public function __invoke(BuildTeacherListPdfAction $action, Request $request): Response
    {
        abort_unless($request->user()?->canAccessPanel(Filament::getPanel('admin')), 403);

        $columns = (array) $request->query('columns', []);
        $orientation = (string) $request->query('orientation', 'P');

        $pdf = $action->handle($columns, $orientation);

        $filename = 'teacher-list-'.now()->format('Y-m-d').'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
