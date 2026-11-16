<?php

namespace App\Support\Concerns;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Shared by every class that generates a PDF via mpdf — the combined-sheet
 * actions and the per-record generation jobs — so the mpdf instance
 * configuration (Bangla font registration, temp dir, pcre limit) exists in
 * exactly one place instead of being repeated at each call site.
 */
trait BuildsMpdfDocuments
{
    /**
     * Build an mpdf instance configured with the app's Bangla font (SolaimanLipi)
     * and temp directory. Also raises PHP's pcre.backtrack_limit as a safety net
     * for large WriteHTML() calls — images themselves are passed as local file
     * paths via PdfImage, not inlined as base64, to keep the HTML small.
     */
    protected function makeMpdf(string $format = 'A4', string $orientation = 'P'): Mpdf
    {
        ini_set('pcre.backtrack_limit', '10000000');

        $fontPath = public_path('fonts');

        return new Mpdf([
            'format' => $format,
            'orientation' => $orientation,
            'fontDir' => array_merge((new ConfigVariables)->getDefaults()['fontDir'], [$fontPath]),
            'fontdata' => (new FontVariables)->getDefaults()['fontdata'] + [
                'solaimanlipi' => ['R' => 'SolaimanLipi.ttf', 'useOTL' => 0xFF],
            ],
            'default_font' => 'solaimanlipi',
            'tempDir' => storage_path('app/mpdf'),
        ]);
    }

    /**
     * Render the given mpdf instance's output as a raw PDF string.
     */
    protected function outputMpdfString(Mpdf $mpdf): string
    {
        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
