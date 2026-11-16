<?php

namespace App\Console\Commands;

use App\Support\PdfImage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('pdf-thumbs:prune {--days=30 : Delete thumbnails not used for this many days}')]
#[Description('Delete cached PDF image thumbnails that have not been used recently.')]
class PrunePdfThumbnails extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDays(max(0, (int) $this->option('days')))->getTimestamp();

        $stale = collect($disk->files(PdfImage::CACHE_DIRECTORY))
            ->filter(fn (string $file): bool => $disk->lastModified($file) < $cutoff)
            ->values();

        $disk->delete($stale->all());

        $this->info("Deleted {$stale->count()} unused PDF thumbnail(s).");

        return self::SUCCESS;
    }
}
