<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Resolves a stored image to a local file path for mpdf's <img src>.
 *
 * Embedding images as base64 data URIs bloats the HTML handed to WriteHTML()
 * (photos straight off a phone are several megabytes) until it trips
 * pcre.backtrack_limit, and costs memory for every encoded copy. Instead,
 * an image larger than the print needs is downscaled once into a thumbnail
 * cache on the local disk and reused by every later PDF; mpdf reads the file
 * itself and embeds each distinct path only once per document.
 *
 * Thumbnails are keyed by the source's path, size and modification time, so
 * replacing a photo produces a fresh thumbnail. Ones not used for a while are
 * removed by the pdf-thumbs:prune command; deleting the whole cache is always
 * safe — thumbnails are rebuilt on demand.
 */
class PdfImage
{
    /**
     * Longest edge, in pixels, an embedded image is scaled down to — sharp
     * enough for a passport-size photo or seal at print resolution.
     */
    public const DEFAULT_MAX_EDGE = 400;

    /**
     * Directory on the local disk holding the thumbnail cache.
     */
    public const CACHE_DIRECTORY = 'pdf-thumbs';

    private const CACHE_DISK = 'local';

    private const JPEG_QUALITY = 85;

    /**
     * Absolute local path of the image to embed — a cached thumbnail when the
     * source is larger than $maxEdge, otherwise the source itself — or null
     * when there is no file.
     */
    public static function path(?string $path, string $disk = 'public', int $maxEdge = self::DEFAULT_MAX_EDGE): ?string
    {
        if (blank($path)) {
            return null;
        }

        $source = Storage::disk($disk);

        if (! $source->exists($path)) {
            return null;
        }

        $cache = Storage::disk(self::CACHE_DISK);
        $key = sha1(implode('|', [$disk, $path, $source->size($path), $source->lastModified($path), $maxEdge]));

        foreach (['jpg', 'png', 'orig'] as $extension) {
            $cached = self::CACHE_DIRECTORY."/{$key}.{$extension}";

            if ($cache->exists($cached)) {
                @touch($cache->path($cached));

                return $cache->path($cached);
            }
        }

        $contents = (string) $source->get($path);
        $thumbnail = self::downscale($contents, $source->mimeType($path) ?: 'image/png', $maxEdge);

        if ($thumbnail !== null) {
            $cached = self::CACHE_DIRECTORY."/{$key}.{$thumbnail['extension']}";
            $cache->put($cached, $thumbnail['contents']);

            return $cache->path($cached);
        }

        // Already small enough (or not decodable by GD, e.g. SVG): embed the
        // source directly when it lives on the local filesystem.
        $sourcePath = $source->path($path);

        if (is_file($sourcePath)) {
            return $sourcePath;
        }

        $cached = self::CACHE_DIRECTORY."/{$key}.orig";
        $cache->put($cached, $contents);

        return $cache->path($cached);
    }

    /**
     * Downscale the image so its longest edge is at most $maxEdge. Returns null
     * when it already fits or GD cannot decode it. Images that may carry
     * transparency become PNG; everything else becomes JPEG.
     *
     * @return array{contents: string, extension: string}|null
     */
    private static function downscale(string $contents, string $mime, int $maxEdge): ?array
    {
        $size = @getimagesizefromstring($contents);

        if ($size === false || max($size[0], $size[1]) <= $maxEdge) {
            return null;
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return null;
        }

        $scale = $maxEdge / max($size[0], $size[1]);
        $width = max(1, (int) round($size[0] * $scale));
        $height = max(1, (int) round($size[1] * $scale));

        $keepsTransparency = in_array($mime, ['image/png', 'image/gif', 'image/webp'], true);

        $target = imagecreatetruecolor($width, $height);

        if ($keepsTransparency) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        } else {
            imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);

        ob_start();

        if ($keepsTransparency) {
            imagepng($target, null, 9);
        } else {
            imagejpeg($target, null, self::JPEG_QUALITY);
        }

        return [
            'contents' => (string) ob_get_clean(),
            'extension' => $keepsTransparency ? 'png' : 'jpg',
        ];
    }
}
