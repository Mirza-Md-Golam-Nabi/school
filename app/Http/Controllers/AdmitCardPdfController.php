<?php

namespace App\Http\Controllers;

use App\Models\AdmitCard;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdmitCardPdfController extends Controller
{
    /**
     * Stream the generated admit card PDF inline so it opens in the browser for viewing/printing.
     */
    public function __invoke(AdmitCard $admitCard): Response|StreamedResponse
    {
        abort_unless($admitCard->is_generated && $admitCard->file_path, 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($admitCard->file_path), 404);

        return $disk->response($admitCard->file_path, "admit-card-{$admitCard->student_id}-{$admitCard->exam_id}.pdf");
    }
}
