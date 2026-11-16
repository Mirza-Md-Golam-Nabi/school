<?php

namespace App\Support;

use App\Models\SchoolSetting;

/**
 * The school's name, address line, logo, seal, signature and footer text as
 * the routine admit card prints them — read once per PDF and shared by the
 * single-card page and the class sheet (images as local paths for mPDF).
 */
class AdmitCardBranding
{
    public function __construct(
        public readonly string $schoolName,
        public readonly string $eiinLine,
        public readonly string $addressLine,
        public readonly ?string $logoSrc,
        public readonly ?string $sealSrc,
        public readonly ?string $signatureSrc,
        public readonly string $footerText,
    ) {}

    public static function fromSettings(): self
    {
        return new self(
            schoolName: (string) SchoolSetting::get('school_name', ''),
            eiinLine: SchoolSetting::eiinLine(),
            addressLine: collect([
                SchoolSetting::get('school_address', ''),
                ($year = SchoolSetting::get('school_established_year', '')) ? "Established: {$year}" : null,
            ])->filter()->implode(' | '),
            logoSrc: self::imagePath(SchoolSetting::get('school_logo')),
            sealSrc: self::imagePath(SchoolSetting::get('school_seal')),
            signatureSrc: self::imagePath(SchoolSetting::get('principal_signature')),
            footerText: (string) SchoolSetting::get('admit_card_footer_text', ''),
        );
    }

    /**
     * A stored image (public disk) as a cached, downscaled local path for mPDF,
     * or null when there is none.
     */
    public static function imagePath(?string $path): ?string
    {
        return PdfImage::path($path);
    }
}
