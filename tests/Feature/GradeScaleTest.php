<?php

use App\Models\GradeScale;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves a grade for every mark from 0 to 100 with no gaps, using the seeded default scale', function () {
    // The old hardcoded Grade::fromMarks() left marks like 79.5 with no grade
    // because its ranges only checked integer boundaries. fromMarks() must
    // never return null for any mark in [0, 100] on the default seed.
    foreach (range(0, 1000) as $tenthMark) {
        $marks = $tenthMark / 10;

        expect(GradeScale::fromMarks($marks))->not->toBeNull();
    }
});

it('resolves the reported edge case: 79.5 marks resolves to grade A, not no grade', function () {
    $grade = GradeScale::fromMarks(79.5);

    expect($grade->letter_grade)->toBe('A')
        ->and($grade->grade_point)->toBe(4.0);
});

it('resolves marks at each default boundary to the correct letter grade', function () {
    expect(GradeScale::fromMarks(100)->letter_grade)->toBe('A+')
        ->and(GradeScale::fromMarks(80)->letter_grade)->toBe('A+')
        ->and(GradeScale::fromMarks(79.99)->letter_grade)->toBe('A')
        ->and(GradeScale::fromMarks(70)->letter_grade)->toBe('A')
        ->and(GradeScale::fromMarks(69.99)->letter_grade)->toBe('A-')
        ->and(GradeScale::fromMarks(60)->letter_grade)->toBe('A-')
        ->and(GradeScale::fromMarks(59.99)->letter_grade)->toBe('B')
        ->and(GradeScale::fromMarks(50)->letter_grade)->toBe('B')
        ->and(GradeScale::fromMarks(49.99)->letter_grade)->toBe('C')
        ->and(GradeScale::fromMarks(40)->letter_grade)->toBe('C')
        ->and(GradeScale::fromMarks(39.99)->letter_grade)->toBe('D')
        ->and(GradeScale::fromMarks(33)->letter_grade)->toBe('D')
        ->and(GradeScale::fromMarks(32.99)->letter_grade)->toBe('F')
        ->and(GradeScale::fromMarks(0)->letter_grade)->toBe('F');
});

it('resolves a gpa value to the matching letter grade', function () {
    expect(GradeScale::fromGpa(5.0)->letter_grade)->toBe('A+')
        ->and(GradeScale::fromGpa(4.25)->letter_grade)->toBe('A')
        ->and(GradeScale::fromGpa(0.0)->letter_grade)->toBe('F');
});

it('reflects an updated grading scale once the cache is flushed', function () {
    GradeScale::query()->delete();
    GradeScale::create(['letter_grade' => 'A+', 'min_mark' => 90, 'max_mark' => 100, 'grade_point' => 5.0, 'color' => 'success']);
    GradeScale::create(['letter_grade' => 'F', 'min_mark' => 0, 'max_mark' => 89.99, 'grade_point' => 0.0, 'color' => 'danger']);
    GradeScale::flushCache();

    expect(GradeScale::fromMarks(85)->letter_grade)->toBe('F')
        ->and(GradeScale::fromMarks(90)->letter_grade)->toBe('A+');
});

it('serves stale data from the 2-minute cache until flushCache() is called', function () {
    GradeScale::fromMarks(85); // warms the cache with the seeded default scale (85 → A+)

    GradeScale::query()->delete();
    GradeScale::create(['letter_grade' => 'F', 'min_mark' => 0, 'max_mark' => 100, 'grade_point' => 0.0, 'color' => 'danger']);

    expect(GradeScale::fromMarks(85)->letter_grade)->toBe('A+');

    GradeScale::flushCache();

    expect(GradeScale::fromMarks(85)->letter_grade)->toBe('F');
});
