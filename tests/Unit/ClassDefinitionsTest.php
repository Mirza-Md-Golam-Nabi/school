<?php

use App\Enums\ClassLevel;
use App\Support\ClassDefinitions;

it('defines the pre-primary classes ordered before class 1, without section or group', function () {
    $classes = collect(ClassDefinitions::prePrimary());

    expect($classes->pluck('name')->all())->toBe(['Play', 'Nursery', 'K.G'])
        ->and($classes->pluck('level')->unique()->all())->toBe([ClassLevel::PrePrimary])
        ->and($classes->pluck('order')->all())->toBe([-2, -1, 0]);
});

it('defines primary classes 1 through 5 without section or group', function () {
    $classes = collect(ClassDefinitions::primary());

    expect($classes->pluck('name')->all())->toBe(['Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5'])
        ->and($classes->pluck('level')->unique()->all())->toBe([ClassLevel::Primary])
        ->and($classes->contains('has_section', true))->toBeFalse()
        ->and($classes->contains('has_group', true))->toBeFalse();
});

it('defines secondary classes 6 through 10 with sections, and groups only from class 9 onward', function () {
    $classes = collect(ClassDefinitions::secondary())->keyBy('name');

    expect($classes->keys()->all())->toBe(['Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10'])
        ->and($classes->every(fn (array $class) => $class['has_section'] === true))->toBeTrue()
        ->and($classes['Class 8']['has_group'])->toBeFalse()
        ->and($classes['Class 9']['has_group'])->toBeTrue()
        ->and($classes['Class 10']['has_group'])->toBeTrue();
});

it('defines college classes 11 and 12 with groups but no sections', function () {
    $classes = collect(ClassDefinitions::college());

    expect($classes->pluck('name')->all())->toBe(['Class 11', 'Class 12'])
        ->and($classes->pluck('level')->unique()->all())->toBe([ClassLevel::College])
        ->and($classes->every(fn (array $class) => $class['has_group'] === true))->toBeTrue()
        ->and($classes->contains('has_section', true))->toBeFalse();
});
