<?php

namespace App\Models;

use Database\Factories\GradeScaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class GradeScale extends Model
{
    /** @use HasFactory<GradeScaleFactory> */
    use HasFactory;

    private const CACHE_KEY = 'grade_scales';

    private const CACHE_TTL_SECONDS = 120;

    protected $fillable = [
        'letter_grade',
        'min_mark',
        'max_mark',
        'grade_point',
        'color',
    ];

    protected $casts = [
        'min_mark' => 'float',
        'max_mark' => 'float',
        'grade_point' => 'float',
    ];

    /**
     * All grade scales, min_mark descending, cached for 2 minutes so exam
     * ranking/marksheet generation (which resolves a grade per subject per
     * student) doesn't hit the database on every lookup.
     *
     * Caches plain attribute arrays, not the model collection itself, and
     * rehydrates models after reading from cache. Eloquent models cached
     * directly under the "database" cache driver can come back as
     * __PHP_Incomplete_Class in a long-running process (e.g. queue:work)
     * where the object graph was serialized in a different request/process.
     *
     * @return Collection<int, self>
     */
    public static function cached(): Collection
    {
        $rows = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn (): array => static::query()
                ->orderByDesc('min_mark')
                ->get()
                ->map(fn (self $scale): array => $scale->attributesToArray())
                ->all()
        );

        return collect($rows)->map(fn (array $row): self => (new self)->forceFill($row));
    }

    /**
     * Call after any create/update/delete on this table so admin changes to
     * the grading scale take effect immediately instead of waiting out the cache.
     */
    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Resolve the grade for a percentage/marks value.
     *
     * Matches by min_mark alone (highest first) rather than checking marks
     * against both min_mark and max_mark — this guarantees every mark from
     * 0-100 resolves to a grade with no gap, regardless of how max_mark was
     * entered (e.g. 79.5 still resolves to the 70+ grade even if its row's
     * max_mark was entered as 79).
     *
     * @param  Collection<int, self>|null  $scales  Pass a pre-loaded collection to
     *                                              avoid the cache lookup when resolving many marks in a loop.
     */
    public static function fromMarks(float $marks, ?Collection $scales = null): ?self
    {
        $scales ??= static::cached();

        return $scales->first(fn (self $scale): bool => $marks >= $scale->min_mark);
    }

    /**
     * Resolve the grade for a GPA value, matching by grade_point (highest first).
     *
     * @param  Collection<int, self>|null  $scales
     */
    public static function fromGpa(float $gpa, ?Collection $scales = null): ?self
    {
        $scales ??= static::cached();

        return $scales->sortByDesc('grade_point')->first(fn (self $scale): bool => $gpa >= $scale->grade_point);
    }
}
