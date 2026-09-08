<?php

use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('fails and creates nothing when no level option is given', function () {
    $this->artisan('create:subject')
        ->assertExitCode(1);

    expect(Subject::count())->toBe(0);
});

it('creates the primary level subjects', function () {
    $this->artisan('create:subject', ['--primary' => true])
        ->assertExitCode(0);

    expect(Subject::where('name', 'Bangla')->exists())->toBeTrue()
        ->and(Subject::where('name', 'ICT')->exists())->toBeTrue()
        // Secondary/college-only subjects must not appear from --primary alone.
        ->and(Subject::where('name', 'Physics')->exists())->toBeFalse();
});

it('creates the secondary and college level subjects together without duplicating shared subjects', function () {
    $this->artisan('create:subject', ['--secondary' => true, '--college' => true])
        ->assertExitCode(0);

    // Physics/Chemistry/Biology/Higher Mathematics appear in both level lists —
    // firstOrCreate must leave exactly one row per name, not one per level.
    expect(Subject::where('name', 'Physics')->count())->toBe(1)
        ->and(Subject::where('name', 'Chemistry')->count())->toBe(1)
        ->and(Subject::where('name', 'Biology')->count())->toBe(1)
        ->and(Subject::where('name', 'Higher Mathematics')->count())->toBe(1)
        // College-only subject.
        ->and(Subject::where('name', 'Logic')->exists())->toBeTrue()
        // Secondary-only subject.
        ->and(Subject::where('name', 'Bangla 1st Paper')->exists())->toBeTrue();
});

it('does not duplicate or overwrite an already-existing subject when run again', function () {
    $existing = Subject::create([
        'name' => 'Bangla',
        'code' => 'CUSTOM-BAN',
        'has_mcq' => false,
        'has_written' => true,
        'has_practical' => false,
        'is_active' => true,
    ]);

    $this->artisan('create:subject', ['--primary' => true])
        ->assertExitCode(0);

    expect(Subject::where('name', 'Bangla')->count())->toBe(1);

    $existing->refresh();

    // firstOrCreate must not touch the existing row's attributes.
    expect($existing->code)->toBe('CUSTOM-BAN')
        ->and($existing->has_mcq)->toBeFalse();
});

it('is idempotent — running the same options twice creates no extra rows', function () {
    $this->artisan('create:subject', ['--primary' => true, '--secondary' => true, '--college' => true])
        ->assertExitCode(0);

    $countAfterFirstRun = Subject::count();

    $this->artisan('create:subject', ['--primary' => true, '--secondary' => true, '--college' => true])
        ->assertExitCode(0);

    expect(Subject::count())->toBe($countAfterFirstRun);
});
