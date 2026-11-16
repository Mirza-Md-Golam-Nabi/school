<?php

use App\Support\AdmitCardBranding;
use App\Support\PdfImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

it('downscales a large photo into a cached jpeg thumbnail', function () {
    $path = UploadedFile::fake()->image('big.jpg', 3000, 4000)->store('student-profiles/photos', 'public');

    $thumbnail = PdfImage::path($path);

    expect($thumbnail)->toEndWith('.jpg')
        ->and(dirname($thumbnail))->toBe(dirname(Storage::disk('local')->path(PdfImage::CACHE_DIRECTORY.'/x')))
        ->and(array_slice(getimagesize($thumbnail), 0, 2))->toBe([300, PdfImage::DEFAULT_MAX_EDGE]);
});

it('keeps a png as png when downscaling', function () {
    $path = UploadedFile::fake()->image('seal.png', 1200, 1200)->store('settings', 'public');

    $thumbnail = PdfImage::path($path);

    expect($thumbnail)->toEndWith('.png')
        ->and(array_slice(getimagesize($thumbnail), 0, 2))->toBe([PdfImage::DEFAULT_MAX_EDGE, PdfImage::DEFAULT_MAX_EDGE]);
});

it('reuses the cached thumbnail instead of resizing again', function () {
    $path = UploadedFile::fake()->image('big.jpg', 2000, 2000)->store('student-profiles/photos', 'public');

    $first = PdfImage::path($path);
    file_put_contents($first, 'cached-marker');

    expect(PdfImage::path($path))->toBe($first)
        ->and(file_get_contents($first))->toBe('cached-marker')
        ->and(Storage::disk('local')->files(PdfImage::CACHE_DIRECTORY))->toHaveCount(1);
});

it('builds a fresh thumbnail when the photo is replaced', function () {
    $path = 'student-profiles/photos/student.jpg';
    Storage::disk('public')->put($path, UploadedFile::fake()->image('a.jpg', 2000, 2000)->getContent());

    $first = PdfImage::path($path);

    Storage::disk('public')->put($path, UploadedFile::fake()->image('b.jpg', 1800, 2400)->getContent());

    $second = PdfImage::path($path);

    expect($second)->not->toBe($first)
        ->and(array_slice(getimagesize($second), 0, 2))->toBe([300, PdfImage::DEFAULT_MAX_EDGE]);
});

it('uses the original file when it already fits, without caching it', function () {
    $path = UploadedFile::fake()->image('small.jpg', 120, 150)->store('student-profiles/photos', 'public');

    expect(PdfImage::path($path))->toBe(Storage::disk('public')->path($path))
        ->and(Storage::disk('local')->files(PdfImage::CACHE_DIRECTORY))->toBeEmpty();
});

it('uses the original file when gd cannot decode it', function () {
    Storage::disk('public')->put('settings/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    expect(PdfImage::path('settings/logo.svg'))->toBe(Storage::disk('public')->path('settings/logo.svg'));
});

it('returns null for a missing or empty path', function () {
    expect(PdfImage::path(null))->toBeNull()
        ->and(PdfImage::path(''))->toBeNull()
        ->and(PdfImage::path('student-profiles/photos/missing.jpg'))->toBeNull();
});

it('resolves admit card branding images through the thumbnail cache', function () {
    $path = UploadedFile::fake()->image('photo.jpg', 2000, 2000)->store('student-profiles/photos', 'public');

    expect(AdmitCardBranding::imagePath($path))->toBe(PdfImage::path($path));
});

it('prunes thumbnails not used for the given number of days', function () {
    $disk = Storage::disk('local');
    $disk->put(PdfImage::CACHE_DIRECTORY.'/old.jpg', 'old');
    $disk->put(PdfImage::CACHE_DIRECTORY.'/fresh.jpg', 'fresh');
    touch($disk->path(PdfImage::CACHE_DIRECTORY.'/old.jpg'), now()->subDays(31)->getTimestamp());

    $this->artisan('pdf-thumbs:prune')
        ->expectsOutput('Deleted 1 unused PDF thumbnail(s).')
        ->assertSuccessful();

    expect($disk->exists(PdfImage::CACHE_DIRECTORY.'/old.jpg'))->toBeFalse()
        ->and($disk->exists(PdfImage::CACHE_DIRECTORY.'/fresh.jpg'))->toBeTrue();
});

it('keeps a thumbnail alive while it is still being used', function () {
    $path = UploadedFile::fake()->image('big.jpg', 2000, 2000)->store('student-profiles/photos', 'public');
    $thumbnail = PdfImage::path($path);
    touch($thumbnail, now()->subDays(40)->getTimestamp());

    PdfImage::path($path);
    clearstatcache();

    $this->artisan('pdf-thumbs:prune')->assertSuccessful();

    expect(file_exists($thumbnail))->toBeTrue();
});
