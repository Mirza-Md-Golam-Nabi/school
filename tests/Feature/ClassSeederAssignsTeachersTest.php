<?php

use App\Enums\ClassLevel;
use App\Models\Classes;
use Database\Seeders\ClassSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TeacherSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('assigns a distinct class teacher to every class', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(TeacherSeeder::class);
    $this->seed(ClassSeeder::class);

    $classes = Classes::query()->get();

    expect($classes)->toHaveCount(10)
        ->and($classes->pluck('class_teacher_id')->filter())->toHaveCount(10)
        ->and($classes->pluck('class_teacher_id')->unique())->toHaveCount(10);
});

it('builds classes 1-10 from the centralized class definitions with the right level, section, and group flags', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(TeacherSeeder::class);
    $this->seed(ClassSeeder::class);

    $classes = Classes::query()->orderBy('order')->get()->keyBy('name');

    expect($classes->keys()->all())->toBe([
        'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5',
        'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10',
    ]);

    foreach (range(1, 5) as $number) {
        $class = $classes["Class {$number}"];
        expect($class->level)->toBe(ClassLevel::Primary)
            ->and($class->has_section)->toBeFalse()
            ->and($class->has_group)->toBeFalse();
    }

    foreach ([6, 7, 8] as $number) {
        $class = $classes["Class {$number}"];
        expect($class->level)->toBe(ClassLevel::Secondary)
            ->and($class->has_section)->toBeTrue()
            ->and($class->has_group)->toBeFalse();
    }

    foreach ([9, 10] as $number) {
        $class = $classes["Class {$number}"];
        expect($class->level)->toBe(ClassLevel::Secondary)
            ->and($class->has_section)->toBeTrue()
            ->and($class->has_group)->toBeTrue();
    }
});
