<?php

namespace App\Support\Concerns;

trait SanitizesFilenames
{
    /**
     * Strip filesystem-unsafe punctuation and turn whitespace into hyphens,
     * without transliterating — class/subject/exam-type names may be in
     * Bangla script, which Str::slug() would strip entirely since it only
     * preserves ASCII.
     */
    protected function sanitizeFilenameSegment(string $value): string
    {
        $value = preg_replace('/[\/\\\\:*?"<>|]+/u', '', trim($value)) ?? $value;

        return preg_replace('/\s+/u', '-', $value) ?? $value;
    }
}
