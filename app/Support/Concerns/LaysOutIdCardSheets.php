<?php

namespace App\Support\Concerns;

use App\Models\SchoolSetting;
use App\Support\IdCardLogo;
use App\Support\PdfImage;

/**
 * The shared geometry of an ID card sheet: CR80 portrait cards, each person's
 * front and back side by side so the pair can be cut out as one piece and
 * folded down the middle, four people per landscape A4 page.
 */
trait LaysOutIdCardSheets
{
    /**
     * Standard CR80 ID card, portrait, in millimetres.
     */
    public const CARD_WIDTH = 54.0;

    public const CARD_HEIGHT = 85.6;

    /**
     * Gap between neighbouring people's cards, both across and down the sheet.
     */
    protected const CARD_GAP = 12.0;

    protected const COLUMNS_PER_PAGE = 2;

    protected const ROWS_PER_PAGE = 2;

    protected const PAGE_WIDTH = 297.0;

    protected const PAGE_HEIGHT = 210.0;

    protected const CARDS_PER_PAGE = self::COLUMNS_PER_PAGE * self::ROWS_PER_PAGE;

    /**
     * Top-left corner, in millimetres from the page edge, of the front card in
     * the given slot. The block of cards is centred on the page.
     *
     * @return array{x: float, y: float}
     */
    protected function slotOrigin(int $slot): array
    {
        $pairWidth = self::CARD_WIDTH * 2;

        $blockWidth = $pairWidth * self::COLUMNS_PER_PAGE + self::CARD_GAP * (self::COLUMNS_PER_PAGE - 1);
        $blockHeight = self::CARD_HEIGHT * self::ROWS_PER_PAGE + self::CARD_GAP * (self::ROWS_PER_PAGE - 1);

        $column = $slot % self::COLUMNS_PER_PAGE;
        $row = intdiv($slot, self::COLUMNS_PER_PAGE);

        return [
            'x' => (self::PAGE_WIDTH - $blockWidth) / 2 + $column * ($pairWidth + self::CARD_GAP),
            'y' => (self::PAGE_HEIGHT - $blockHeight) / 2 + $row * (self::CARD_HEIGHT + self::CARD_GAP),
        ];
    }

    /**
     * School details shared by every card, resolved once per PDF. The logo is
     * recoloured when the card design has a logo colour.
     *
     * @return array{name: string, address: string, logo: ?string, signature: ?string}
     */
    protected function schoolBranding(?string $logoColour = null): array
    {
        return [
            'name' => (string) SchoolSetting::get('school_name', ''),
            'address' => (string) SchoolSetting::get('school_address', ''),
            'logo' => IdCardLogo::path($logoColour),
            'signature' => PdfImage::path(SchoolSetting::get('principal_signature')),
        ];
    }
}
