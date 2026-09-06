<?php

use App\Models\Subject;
use App\Support\SubjectDefinitions;
use Database\Seeders\SubjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates every subject from the centralized definitions, deduped by name', function () {
    $this->seed(SubjectSeeder::class);

    $expectedNames = collect([
        ...SubjectDefinitions::primary(),
        ...SubjectDefinitions::secondary(),
        ...SubjectDefinitions::college(),
    ])->pluck('name')->unique()->values();

    expect(Subject::count())->toBe($expectedNames->count());

    foreach ($expectedNames as $name) {
        expect(Subject::where('name', $name)->count())->toBe(1);
    }
});

it('respects each subject definition\'s has_practical flag instead of always defaulting to false', function () {
    $this->seed(SubjectSeeder::class);

    expect(Subject::where('name', 'Physics')->firstOrFail()->has_practical)->toBeTrue()
        ->and(Subject::where('name', 'Bangla')->firstOrFail()->has_practical)->toBeFalse();
});

it('does not duplicate or overwrite an already-existing subject when seeded again', function () {
    $existing = Subject::create([
        'name' => 'Bangla',
        'code' => 'CUSTOM-BAN',
        'has_mcq' => false,
        'has_written' => true,
        'has_practical' => false,
        'is_active' => true,
    ]);

    $this->seed(SubjectSeeder::class);

    expect(Subject::where('name', 'Bangla')->count())->toBe(1);

    $existing->refresh();

    expect($existing->code)->toBe('CUSTOM-BAN')
        ->and($existing->has_mcq)->toBeFalse();
});
