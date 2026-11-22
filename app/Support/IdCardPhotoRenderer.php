<?php

namespace App\Support;

use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Turns a student's avatar into the round, ring-framed portrait printed on the
 * ID card. mpdf can neither clip an image to a circle nor layer one element
 * over another, so the gold ring, the white inner ring, and the circular crop
 * are all baked into a single transparent PNG here. The photo is shrunk to fit
 * inside both rings, so no part of it is hidden under them.
 */
class IdCardPhotoRenderer
{
    /**
     * Output edge in pixels — ~330dpi at the 23mm the card prints it at.
     */
    public const SIZE = 300;

    /**
     * Ring widths in pixels of SIZE — the browser previews draw the same rings
     * at the same proportions.
     */
    public const GOLD_RING_WIDTH = 7.0;

    public const WHITE_RING_WIDTH = 2.5;

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
     * @param  IdCardPhotoPosition|null  $position  where the photo sits in the frame — the default position when null
     */
    public function render(?string $avatarPath, string $disk = 'public', array $ringColour = self::GOLD, ?IdCardPhotoPosition $position = null): string
    {
        $photo = $this->loadSquarePhoto($avatarPath, $disk, $position ?? new IdCardPhotoPosition);

        if ($photo === null) {
            return $this->placeholderDataUris[implode(',', $ringColour)] ??= $this->frame($this->drawPlaceholder(), $ringColour);
        }

        return $this->frame($photo, $ringColour);
    }

    /**
     * Load the avatar and cut the square the position asks for, scaled to SIZE x SIZE.
     */
    private function loadSquarePhoto(?string $avatarPath, string $disk, IdCardPhotoPosition $position): ?GdImage
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

        $crop = $position->cropFor(imagesx($source), imagesy($source));

        $square = imagecreatetruecolor(self::SIZE, self::SIZE);

        imagecopyresampled(
            $square,
            $source,
            0,
            0,
            $crop['x'],
            $crop['y'],
            self::SIZE,
            self::SIZE,
            $crop['edge'],
            $crop['edge'],
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
     * Shrink the square photo into the disc inside the rings, clip it to that
     * circle and wrap it in the white and coloured rings, returning the result
     * as a PNG data URI. Only the pixels outside the photo's safe inner disc
     * are visited — the interior is left untouched.
     *
     * @param  array{0: int, 1: int, 2: int}  $ringColour
     */
    private function frame(GdImage $square, array $ringColour): string
    {
        $centre = (self::SIZE - 1) / 2;
        $outerRadius = self::SIZE / 2;
        $whiteRadius = $outerRadius - self::GOLD_RING_WIDTH;
        $photoRadius = $whiteRadius - self::WHITE_RING_WIDTH;
        $untouchedRadius = $photoRadius - 1;

        $photoEdge = (int) round($photoRadius * 2);
        $photoOffset = intdiv(self::SIZE - $photoEdge, 2);

        $photo = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($photo, 0, 0, imagecolorallocate($photo, ...self::WHITE));
        imagecopyresampled($photo, $square, $photoOffset, $photoOffset, 0, 0, $photoEdge, $photoEdge, imagesx($square), imagesy($square));

        imagealphablending($photo, false);
        imagesavealpha($photo, true);

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
