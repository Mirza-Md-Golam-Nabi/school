<?php

use App\Actions\ResolveMergedSubjectOutcome;
use App\Enums\MergedAbsentRule;
use App\Enums\MergedComponentPassRule;
use App\Enums\MergedPassRule;
use App\Enums\ResultRounding;
use App\Models\ExamSubjectConfig;
use App\Models\ExamSubjectMerge;
use App\Models\GradeScale;
use App\Models\StudentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $rules
 */
function mergedOutcomeMerge(array $rules = []): ExamSubjectMerge
{
    return new ExamSubjectMerge([
        'name' => 'Bangla',
        'pass_rule' => MergedPassRule::Combined,
        'component_pass_rule' => MergedComponentPassRule::Combined,
        'absent_rule' => MergedAbsentRule::Fail,
        'rounding' => ResultRounding::Decimal,
        ...$rules,
    ]);
}

function mergedOutcomePaper(?float $written, ?float $mcq = null): StudentResult
{
    return new StudentResult([
        'mcq_marks' => $mcq,
        'written_marks' => $written,
        'total_marks' => $written === null ? 0 : $written + ($mcq ?? 0),
        'is_absent' => $written === null,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function mergedOutcomeConfig(int $total = 100, int $passMark = 33, array $attributes = []): ExamSubjectConfig
{
    return new ExamSubjectConfig(['total_marks' => $total, 'pass_mark' => $passMark, 'written_total' => $total, ...$attributes]);
}

/**
 * @return array{marks: float, full_marks: float, grade: ?GradeScale, is_fail: bool, is_absent: bool}
 */
function resolveMergedOutcome(ExamSubjectMerge $merge, ?StudentResult $first, ?StudentResult $second, ?ExamSubjectConfig $firstConfig = null, ?ExamSubjectConfig $secondConfig = null): array
{
    return app(ResolveMergedSubjectOutcome::class)->execute(
        $merge,
        $first,
        $second,
        $firstConfig ?? mergedOutcomeConfig(),
        $secondConfig ?? mergedOutcomeConfig(),
        GradeScale::cached(),
    );
}

it('merges two equal papers into marks out of 100 and grades the result', function () {
    $outcome = resolveMergedOutcome(mergedOutcomeMerge(), mergedOutcomePaper(60), mergedOutcomePaper(70));

    expect($outcome['marks'])->toBe(65.0)
        ->and($outcome['full_marks'])->toBe(100.0)
        ->and($outcome['grade']->letter_grade)->toBe('A-')
        ->and($outcome['is_fail'])->toBeFalse();
});

it('weighs papers by their full marks when they differ', function () {
    // (80 + 20) ÷ (100 + 50) × 100 = 66.67 — not (80 + 20) ÷ 2 = 50.
    $outcome = resolveMergedOutcome(
        mergedOutcomeMerge(),
        mergedOutcomePaper(80),
        mergedOutcomePaper(20),
        mergedOutcomeConfig(100, 33),
        mergedOutcomeConfig(50, 17),
    );

    expect($outcome['marks'])->toBe(66.67)
        ->and($outcome['grade']->letter_grade)->toBe('A-')
        ->and($outcome['is_fail'])->toBeFalse();
});

it('rounds the merged marks by the merge rounding', function (ResultRounding $rounding, float $expected) {
    // (75 + 80) ÷ 200 × 100 = 77.5
    $outcome = resolveMergedOutcome(mergedOutcomeMerge(['rounding' => $rounding]), mergedOutcomePaper(75), mergedOutcomePaper(80));

    expect($outcome['marks'])->toBe($expected);
})->with([
    'decimal' => [ResultRounding::Decimal, 77.5],
    'nearest' => [ResultRounding::Nearest, 78.0],
    'up' => [ResultRounding::Up, 78.0],
    'down' => [ResultRounding::Down, 77.0],
]);

it('grades from the rounded marks', function () {
    // (79 + 80) ÷ 200 × 100 = 79.5 → A when kept, A+ when rounded up to 80.
    $kept = resolveMergedOutcome(mergedOutcomeMerge(), mergedOutcomePaper(79), mergedOutcomePaper(80));
    $rounded = resolveMergedOutcome(mergedOutcomeMerge(['rounding' => ResultRounding::Nearest]), mergedOutcomePaper(79), mergedOutcomePaper(80));

    expect($kept['grade']->letter_grade)->toBe('A')
        ->and($rounded['grade']->letter_grade)->toBe('A+');
});

it('fails the subject when one paper is absent under the fail rule', function () {
    $outcome = resolveMergedOutcome(mergedOutcomeMerge(), mergedOutcomePaper(70), mergedOutcomePaper(null));

    expect($outcome['is_fail'])->toBeTrue()
        ->and($outcome['is_absent'])->toBeFalse()
        ->and($outcome['grade']->letter_grade)->toBe('F');
});

it('counts an absent paper as 0 under the count-as-zero rule', function () {
    // 70 + 0 = 70 of 200 → 35, D — the combined pass mark (66 of 200) is met.
    $outcome = resolveMergedOutcome(
        mergedOutcomeMerge(['absent_rule' => MergedAbsentRule::CountAsZero]),
        mergedOutcomePaper(70),
        mergedOutcomePaper(null),
    );

    expect($outcome['marks'])->toBe(35.0)
        ->and($outcome['grade']->letter_grade)->toBe('D')
        ->and($outcome['is_fail'])->toBeFalse();
});

it('treats a paper with no result as absent', function () {
    $outcome = resolveMergedOutcome(
        mergedOutcomeMerge(['absent_rule' => MergedAbsentRule::CountAsZero]),
        mergedOutcomePaper(70),
        null,
    );

    expect($outcome['marks'])->toBe(35.0)
        ->and($outcome['is_fail'])->toBeFalse();
});

it('marks the subject absent only when both papers are absent', function () {
    $outcome = resolveMergedOutcome(mergedOutcomeMerge(), mergedOutcomePaper(null), mergedOutcomePaper(null));

    expect($outcome['is_absent'])->toBeTrue()
        ->and($outcome['is_fail'])->toBeTrue();
});

it('fails on combined marks below the combined pass mark', function () {
    $outcome = resolveMergedOutcome(mergedOutcomeMerge(), mergedOutcomePaper(30), mergedOutcomePaper(30));

    expect($outcome['marks'])->toBe(30.0)
        ->and($outcome['is_fail'])->toBeTrue();
});

it('lets a weak paper pass on combined marks but not when each paper must pass', function () {
    // 80 + 20 = 50: passes combined, but the 2nd paper misses its own 33.
    $combined = resolveMergedOutcome(mergedOutcomeMerge(), mergedOutcomePaper(80), mergedOutcomePaper(20));
    $eachPaper = resolveMergedOutcome(mergedOutcomeMerge(['pass_rule' => MergedPassRule::EachPaper]), mergedOutcomePaper(80), mergedOutcomePaper(20));

    expect($combined['is_fail'])->toBeFalse()
        ->and($combined['grade']->letter_grade)->toBe('B')
        ->and($eachPaper['is_fail'])->toBeTrue()
        ->and($eachPaper['grade']->letter_grade)->toBe('F');
});

it('checks MCQ pass marks on the papers added together or per paper', function () {
    $config = mergedOutcomeConfig(100, 33, ['mcq_total' => 30, 'mcq_pass_mark' => 10, 'written_total' => 70, 'check_mcq_pass' => true]);
    $first = mergedOutcomePaper(50, 15);
    $second = mergedOutcomePaper(50, 8);

    // MCQ 15 + 8 = 23 ≥ 10 + 10 combined; the 2nd paper's 8 < 10 on its own.
    $combined = resolveMergedOutcome(mergedOutcomeMerge(), $first, $second, $config, $config);
    $eachPaper = resolveMergedOutcome(mergedOutcomeMerge(['component_pass_rule' => MergedComponentPassRule::EachPaper]), $first, $second, $config, $config);

    expect($combined['is_fail'])->toBeFalse()
        ->and($eachPaper['is_fail'])->toBeTrue();
});

it('fails combined MCQ marks that miss the added pass marks', function () {
    $config = mergedOutcomeConfig(100, 33, ['mcq_total' => 30, 'mcq_pass_mark' => 10, 'written_total' => 70, 'check_mcq_pass' => true]);

    $outcome = resolveMergedOutcome(mergedOutcomeMerge(), mergedOutcomePaper(60, 9), mergedOutcomePaper(60, 9), $config, $config);

    expect($outcome['is_fail'])->toBeTrue();
});

it('merges results built by a result rule using their final marks', function () {
    $weighted = fn (float $finalMarks): StudentResult => new StudentResult([
        'total_marks' => 0,
        'is_absent' => true,
        'final_marks' => $finalMarks,
        'final_full_marks' => 100,
        'result_breakdown' => [],
    ]);

    $outcome = resolveMergedOutcome(mergedOutcomeMerge(), $weighted(70), $weighted(90));

    expect($outcome['marks'])->toBe(80.0)
        ->and($outcome['grade']->letter_grade)->toBe('A+')
        ->and($outcome['is_fail'])->toBeFalse();
});
