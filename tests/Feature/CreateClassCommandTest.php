<?php

use App\Enums\ClassLevel;
use App\Models\Classes;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('fails and creates nothing when no level option is given', function () {
    $this->artisan('create:class')
        ->assertExitCode(1);

    expect(Classes::count())->toBe(0);
});

it('creates the pre-primary/kindergarten classes without section or group, ordered before class 1', function () {
    $this->artisan('create:class', ['--pre-primary' => true])
        ->assertExitCode(0);

    expect(Classes::count())->toBe(3);

    $play = Classes::where('name', 'Play')->firstOrFail();
    $nursery = Classes::where('name', 'Nursery')->firstOrFail();
    $kg = Classes::where('name', 'K.G')->firstOrFail();

    foreach ([$play, $nursery, $kg] as $class) {
        expect($class->level)->toBe(ClassLevel::PrePrimary)
            ->and($class->has_section)->toBeFalse()
            ->and($class->has_group)->toBeFalse();
    }

    expect($play->order)->toBeLessThan($nursery->order)
        ->and($nursery->order)->toBeLessThan($kg->order);

    // Primary-only classes must not appear from --pre-primary alone.
    expect(Classes::where('name', 'Class 1')->exists())->toBeFalse();
});

it('creates the primary level classes without section or group', function () {
    $this->artisan('create:class', ['--primary' => true])
        ->assertExitCode(0);

    expect(Classes::count())->toBe(5);

    $classOne = Classes::where('name', 'Class 1')->firstOrFail();

    expect($classOne->level)->toBe(ClassLevel::Primary)
        ->and($classOne->order)->toBe(1)
        ->and($classOne->has_section)->toBeFalse()
        ->and($classOne->has_group)->toBeFalse();

    // Secondary/college-only classes must not appear from --primary alone.
    expect(Classes::where('name', 'Class 6')->exists())->toBeFalse();
});

it('creates the secondary level classes with sections, and groups only from class 9 onward', function () {
    $this->artisan('create:class', ['--secondary' => true])
        ->assertExitCode(0);

    expect(Classes::count())->toBe(5);

    $classSeven = Classes::where('name', 'Class 7')->firstOrFail();
    $classNine = Classes::where('name', 'Class 9')->firstOrFail();

    expect($classSeven->level)->toBe(ClassLevel::Secondary)
        ->and($classSeven->has_section)->toBeTrue()
        ->and($classSeven->has_group)->toBeFalse();

    expect($classNine->has_section)->toBeTrue()
        ->and($classNine->has_group)->toBeTrue();
});

it('creates the college level classes 11 and 12 with groups but no sections', function () {
    $this->artisan('create:class', ['--college' => true])
        ->assertExitCode(0);

    expect(Classes::count())->toBe(2);

    $classEleven = Classes::where('name', 'Class 11')->firstOrFail();
    $classTwelve = Classes::where('name', 'Class 12')->firstOrFail();

    foreach ([$classEleven, $classTwelve] as $class) {
        expect($class->level)->toBe(ClassLevel::College)
            ->and($class->has_section)->toBeFalse()
            ->and($class->has_group)->toBeTrue();
    }

    expect($classEleven->order)->toBe(11)
        ->and($classTwelve->order)->toBe(12);
});

it('does not duplicate or overwrite an already-existing class when run again', function () {
    $existing = Classes::create([
        'name' => 'Class 1',
        'level' => ClassLevel::Primary,
        'order' => 99,
        'has_section' => true,
        'has_group' => true,
        'is_active' => true,
    ]);

    $this->artisan('create:class', ['--primary' => true])
        ->assertExitCode(0);

    expect(Classes::where('name', 'Class 1')->count())->toBe(1);

    $existing->refresh();

    // firstOrCreate must not touch the existing row's attributes.
    expect($existing->order)->toBe(99)
        ->and($existing->has_section)->toBeTrue()
        ->and($existing->has_group)->toBeTrue();
});

it('orders K.G right before class 1 when both are created together', function () {
    $this->artisan('create:class', ['--pre-primary' => true, '--primary' => true])
        ->assertExitCode(0);

    $kg = Classes::where('name', 'K.G')->firstOrFail();
    $classOne = Classes::where('name', 'Class 1')->firstOrFail();

    expect($kg->order)->toBeLessThan($classOne->order);
});

it('is idempotent — running the same options twice creates no extra rows', function () {
    $this->artisan('create:class', ['--pre-primary' => true, '--primary' => true, '--secondary' => true, '--college' => true])
        ->assertExitCode(0);

    $countAfterFirstRun = Classes::count();

    $this->artisan('create:class', ['--pre-primary' => true, '--primary' => true, '--secondary' => true, '--college' => true])
        ->assertExitCode(0);

    expect(Classes::count())->toBe($countAfterFirstRun);
});
