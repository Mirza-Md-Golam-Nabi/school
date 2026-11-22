<?php

namespace App\Support;

use App\Models\SchoolSetting;
use Illuminate\Support\Facades\Storage;

/**
 * The school logo as printed on ID cards — either as uploaded, or recoloured
 * to the single colour the school picked for its card design.
 *
 * Recolouring keeps the logo's shape: each pixel's ink (how dark and how
 * opaque it is) becomes that much of the chosen colour, so a black logo on a
 * white or transparent background comes out in the new colour on a
 * transparent one. Recoloured logos are cached beside PdfImage's thumbnails.
 */
class IdCardLogo
{
    /**
     * A colour is stored and accepted only as #rrggbb.
     */
    public const COLOUR_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    /**
     * Absolute local path of the logo for mpdf, or null when the school has none.
     */
    public static function path(?string $colour = null): ?string
    {
        $original = PdfImage::path(SchoolSetting::get('school_logo'));

        if ($original === null || ! self::isValidColour($colour)) {
            return $original;
        }

        $cache = Storage::disk('local');
        $cached = PdfImage::CACHE_DIRECTORY.'/logo-'.sha1(implode('|', [$original, @filemtime($original), strtolower($colour)])).'.png';

        if ($cache->exists($cached)) {
            return $cache->path($cached);
        }

        $recoloured = self::recolour((string) @file_get_contents($original), $colour);

        if ($recoloured === null) {
            return $original;
        }

        $cache->put($cached, $recoloured);

        return $cache->path($cached);
    }

    /**
     * The same logo as a data URI, for the browser previews.
     */
    public static function dataUri(?string $colour = null): ?string
    {
        $path = self::path($colour);

        if ($path === null || ! is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return "data:{$mime};base64,".base64_encode((string) file_get_contents($path));
    }

    public static function isValidColour(mixed $colour): bool
    {
        return is_string($colour) && preg_match(self::COLOUR_PATTERN, $colour) === 1;
    }

    /**
     * PNG bytes of the logo painted in the given colour, or null when GD cannot read it.
     */
    private static function recolour(string $contents, string $colour): ?string
    {
        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return null;
        }

        [$red, $green, $blue] = sscanf($colour, '#%02x%02x%02x');

        $width = imagesx($source);
        $height = imagesy($source);

        $target = imagecreatetruecolor($width, $height);
        imagealphablending($target, false);
        imagesavealpha($target, true);

        $palette = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel = imagecolorsforindex($source, imagecolorat($source, $x, $y));

                $opacity = 1 - $pixel['alpha'] / 127;
                $luminance = (0.299 * $pixel['red'] + 0.587 * $pixel['green'] + 0.114 * $pixel['blue']) / 255;
                $ink = $opacity * (1 - $luminance);

                $transparency = (int) round(127 * (1 - $ink));

                $palette[$transparency] ??= imagecolorallocatealpha($target, $red, $green, $blue, $transparency);

                imagesetpixel($target, $x, $y, $palette[$transparency]);
            }
        }

        ob_start();
        imagepng($target, null, 9);

        return (string) ob_get_clean();
    }
}
