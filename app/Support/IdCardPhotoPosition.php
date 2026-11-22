<?php

namespace App\Support;

/**
 * Where a portrait sits inside the round ID card frame. The photo is first
 * scaled so its shorter side fills the frame, then enlarged by the zoom; x and
 * y say how far across the leftover width and height the visible square is
 * taken from — 0 is the left/top edge, 100 the right/bottom edge — exactly
 * like CSS object-position, so the browser preview and the PDF agree.
 */
final readonly class IdCardPhotoPosition
{
    public const MIN_ZOOM = 1.0;

    public const MAX_ZOOM = 3.0;

    /**
     * The default keeps the top third of a tall photo, so the face rather
     * than the chest lands in the frame.
     */
    public const DEFAULT_X = 50.0;

    public const DEFAULT_Y = 100 / 3;

    public float $x;

    public float $y;

    public float $zoom;

    public function __construct(float $x = self::DEFAULT_X, float $y = self::DEFAULT_Y, float $zoom = self::MIN_ZOOM)
    {
        $this->x = max(0.0, min(100.0, $x));
        $this->y = max(0.0, min(100.0, $y));
        $this->zoom = max(self::MIN_ZOOM, min(self::MAX_ZOOM, $zoom));
    }

    /**
     * Build from a stored position, falling back to the default for anything
     * missing or out of range.
     *
     * @param  array{x?: mixed, y?: mixed, zoom?: mixed}|null  $position
     */
    public static function fromArray(?array $position): self
    {
        $number = fn (string $key, float $default): float => is_numeric($position[$key] ?? null) ? (float) $position[$key] : $default;

        return new self(
            $number('x', self::DEFAULT_X),
            $number('y', self::DEFAULT_Y),
            $number('zoom', self::MIN_ZOOM),
        );
    }

    /**
     * @return array{x: float, y: float, zoom: float}
     */
    public function toArray(): array
    {
        return [
            'x' => round($this->x, 2),
            'y' => round($this->y, 2),
            'zoom' => round($this->zoom, 3),
        ];
    }

    /**
     * The square, in source pixels, cut out of a photo of this size.
     *
     * @return array{x: int, y: int, edge: int}
     */
    public function cropFor(int $sourceWidth, int $sourceHeight): array
    {
        $edge = max(1, (int) round(min($sourceWidth, $sourceHeight) / $this->zoom));

        return [
            'x' => (int) round(($sourceWidth - $edge) * $this->x / 100),
            'y' => (int) round(($sourceHeight - $edge) * $this->y / 100),
            'edge' => $edge,
        ];
    }
}
