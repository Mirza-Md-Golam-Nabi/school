<?php

namespace App\Actions;

use App\Enums\AdmitCardDesign;
use App\Models\AdmitCard;
use App\Support\Concerns\BuildsMpdfDocuments;

class RenderAdmitCardPdfAction
{
    use BuildsMpdfDocuments;

    /**
     * One admit card as a PDF, in its design and page size — the only place
     * a single card is rendered, so a student's card and the sample the admin
     * looks at before choosing a design are always the same.
     */
    public function handle(AdmitCard $admitCard): string
    {
        $design = $admitCard->design ?? AdmitCardDesign::Simple;

        // The routine card is wide (two routine columns side by side): on A5
        // it fills a landscape page, on A4 it sits on the top half.
        $orientation = $design === AdmitCardDesign::WithRoutine && $admitCard->page_size === 'A5' ? 'L' : 'P';

        $mpdf = $this->makeMpdf($admitCard->page_size ?? 'A4', $orientation);
        $mpdf->WriteHTML(view($design->view(), ['admitCard' => $admitCard])->render());

        return $this->outputMpdfString($mpdf);
    }
}
