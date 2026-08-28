<?php

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
