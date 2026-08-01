<?php

namespace App\Http\Controllers;

use App\Models\Marksheet;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarksheetPdfController extends Controller
{
    /**
     * Stream the generated marksheet PDF inline so it opens in the browser for viewing/printing.
     */
    public function __invoke(Marksheet $marksheet): Response|StreamedResponse
    {
        abort_unless($marksheet->is_generated && $marksheet->file_path, 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($marksheet->file_path), 404);

        return $disk->response($marksheet->file_path, "marksheet-{$marksheet->student_id}-{$marksheet->exam_id}.pdf");
    }
}
