<?php

namespace App\Support;

use Mpdf\QrCode\Output\Svg;
use Mpdf\QrCode\QrCode;

/**
 * Draws a QR code as an inline SVG image for on-screen card previews. PDFs
 * draw theirs with mPDF's own <barcode type="QR"> tag instead.
 */
class QrCodeImage
{
    /**
     * The QR code for $value as a data URI, without the quiet zone — the
     * caller puts it on a white background.
     */
    public static function svgDataUri(string $value, string $errorLevel = 'M'): string
    {
        $qrCode = new QrCode($value, $errorLevel);
        $qrCode->disableBorder();

        return 'data:image/svg+xml;base64,'.base64_encode((new Svg)->output($qrCode, 200));
    }
}
