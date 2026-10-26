<?php

namespace App\Support;

use App\Models\SchoolSetting;
use Illuminate\Support\Facades\Storage;

/**
 * The school's name, address line, logo, seal, signature and footer text as
 * the routine admit card prints them — read once per PDF and shared by the
 * single-card page and the class sheet (images as data URIs for mPDF).
 */
class AdmitCardBranding
{
    public function __construct(
        public readonly string $schoolName,
        public readonly string $addressLine,
        public readonly ?string $logoDataUri,
        public readonly ?string $sealDataUri,
        public readonly ?string $signatureDataUri,
        public readonly string $footerText,
    ) {}

    public static function fromSettings(): self
    {
        return new self(
            schoolName: (string) SchoolSetting::get('school_name', ''),
            addressLine: collect([
                SchoolSetting::get('school_address', ''),
                ($year = SchoolSetting::get('school_established_year', '')) ? "Established: {$year}" : null,
            ])->filter()->implode(' | '),
            logoDataUri: self::dataUri(SchoolSetting::get('school_logo')),
            sealDataUri: self::dataUri(SchoolSetting::get('school_seal')),
            signatureDataUri: self::dataUri(SchoolSetting::get('principal_signature')),
            footerText: (string) SchoolSetting::get('admit_card_footer_text', ''),
        );
    }

    /**
     * A stored image (public disk) as a data URI, or null when there is none.
     */
    public static function dataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $disk = Storage::disk('public');

        return 'data:'.($disk->mimeType($path) ?: 'image/png').';base64,'.base64_encode($disk->get($path));
    }
}
