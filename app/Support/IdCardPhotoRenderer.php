<?php

namespace App\Support;

use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Turns a student's avatar into the round, ring-framed portrait printed on the
 * ID card. mpdf can neither clip an image to a circle nor layer one element
 * over another, so the gold ring, the white inner ring, and the circular crop
 * are all baked into a single transparent PNG here.
 */
class IdCardPhotoRenderer
{
    /**
     * Output edge in pixels — ~330dpi at the 23mm the card prints it at.
     */
    private const SIZE = 300;

    private const GOLD_RING_WIDTH = 7.0;

    private const WHITE_RING_WIDTH = 9.0;

    /** @var array{0: int, 1: int, 2: int} */
    private const GOLD = [245, 185, 66];

    /** @var array{0: int, 1: int, 2: int} */
    private const WHITE = [255, 255, 255];

    /** @var array{0: int, 1: int, 2: int} */
    private const PLACEHOLDER_BACKGROUND = [224, 231, 255];

    /** @var array{0: int, 1: int, 2: int} */
    private const PLACEHOLDER_FIGURE = [165, 180, 252];

    /**
     * The framed silhouette per ring colour — every photoless student on a
     * sheet shares one.
     *
     * @var array<string, string>
     */
    private array $placeholderDataUris = [];

    /**
     * Render the framed portrait for the avatar stored at this path, falling
     * back to a silhouette when there is no avatar or it cannot be decoded.
     *
     * @param  array{0: int, 1: int, 2: int}  $ringColour  RGB of the outer ring — each card design has its own
     */
    public function render(?string $avatarPath, string $disk = 'public', array $ringColour = self::GOLD): string
    {
        $photo = $this->loadSquarePhoto($avatarPath, $disk);

        if ($photo === null) {
            return $this->placeholderDataUris[implode(',', $ringColour)] ??= $this->frame($this->drawPlaceholder(), $ringColour);
        }

        return $this->frame($photo, $ringColour);
    }

    /**
     * Load the avatar and cover-crop it to a SIZE x SIZE square.
     */
    private function loadSquarePhoto(?string $avatarPath, string $disk): ?GdImage
    {
        if (blank($avatarPath)) {
            return null;
        }

        $storage = Storage::disk($disk);

        if (! $storage->exists($avatarPath)) {
            return null;
        }

        $source = @imagecreatefromstring((string) $storage->get($avatarPath));

        if ($source === false) {
            return null;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $cropEdge = min($sourceWidth, $sourceHeight);

        $square = imagecreatetruecolor(self::SIZE, self::SIZE);

        // Portraits are cropped from the top third rather than dead centre, so
        // a tall photo keeps the face instead of the chest.
        imagecopyresampled(
            $square,
            $source,
            0,
            0,
            intdiv($sourceWidth - $cropEdge, 2),
            intdiv($sourceHeight - $cropEdge, 3),
            self::SIZE,
            self::SIZE,
            $cropEdge,
            $cropEdge,
        );

        return $square;
    }

    /**
     * Draw the head-and-shoulders silhouette used when a student has no photo.
     * Drawn oversized and scaled down, since GD does not antialias filled ellipses.
     */
    private function drawPlaceholder(): GdImage
    {
        $scale = 3;
        $edge = self::SIZE * $scale;

        $canvas = imagecreatetruecolor($edge, $edge);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, ...self::PLACEHOLDER_BACKGROUND));

        $figure = imagecolorallocate($canvas, ...self::PLACEHOLDER_FIGURE);
        $centre = intdiv($edge, 2);

        imagefilledellipse($canvas, $centre, (int) ($edge * 0.40), (int) ($edge * 0.30), (int) ($edge * 0.30), $figure);
        imagefilledellipse($canvas, $centre, (int) ($edge * 0.92), (int) ($edge * 0.62), (int) ($edge * 0.62), $figure);

        $placeholder = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled($placeholder, $canvas, 0, 0, 0, 0, self::SIZE, self::SIZE, $edge, $edge);

        return $placeholder;
    }

    /**
     * Clip the square photo to a circle and wrap it in the white and coloured
     * rings, returning the result as a PNG data URI. Only the pixels outside
     * the photo's safe inner disc are visited — the interior is left untouched.
     *
     * @param  array{0: int, 1: int, 2: int}  $ringColour
     */
    private function frame(GdImage $photo, array $ringColour): string
    {
        imagealphablending($photo, false);
        imagesavealpha($photo, true);

        $centre = (self::SIZE - 1) / 2;
        $outerRadius = self::SIZE / 2;
        $whiteRadius = $outerRadius - self::GOLD_RING_WIDTH;
        $photoRadius = $whiteRadius - self::WHITE_RING_WIDTH;
        $untouchedRadius = $photoRadius - 1;

        for ($y = 0; $y < self::SIZE; $y++) {
            $dy = $y - $centre;
            $untouchedHalfSpan = $untouchedRadius ** 2 > $dy ** 2
                ? sqrt($untouchedRadius ** 2 - $dy ** 2)
                : 0;

            for ($x = 0; $x < self::SIZE; $x++) {
                if ($untouchedHalfSpan > 0 && abs($x - $centre) < $untouchedHalfSpan) {
                    $x = (int) ceil($centre + $untouchedHalfSpan) - 1;

                    continue;
                }

                $distance = sqrt(($x - $centre) ** 2 + $dy ** 2);
                $pixel = imagecolorat($photo, $x, $y);
                $colour = [($pixel >> 16) & 0xFF, ($pixel >> 8) & 0xFF, $pixel & 0xFF];

                $colour = $this->blend($colour, self::WHITE, $this->coverage($distance, $photoRadius));
                $colour = $this->blend($colour, $ringColour, $this->coverage($distance, $whiteRadius));
                $transparency = (int) round(127 * $this->coverage($distance, $outerRadius));

                imagesetpixel($photo, $x, $y, imagecolorallocatealpha($photo, $colour[0], $colour[1], $colour[2], $transparency));
            }
        }

        ob_start();
        imagepng($photo, null, 9);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    /**
     * How far past the given radius a pixel is, as an antialiased 0..1 ramp
     * one pixel wide: 0 well inside the circle, 1 well outside it.
     */
    private function coverage(float $distance, float $radius): float
    {
        return max(0.0, min(1.0, $distance - $radius + 0.5));
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $from
     * @param  array{0: int, 1: int, 2: int}  $to
     * @return array{0: int, 1: int, 2: int}
     */
    private function blend(array $from, array $to, float $amount): array
    {
        if ($amount <= 0.0) {
            return $from;
        }

        return [
            (int) round($from[0] + ($to[0] - $from[0]) * $amount),
            (int) round($from[1] + ($to[1] - $from[1]) * $amount),
            (int) round($from[2] + ($to[2] - $from[2]) * $amount),
        ];
    }
}
