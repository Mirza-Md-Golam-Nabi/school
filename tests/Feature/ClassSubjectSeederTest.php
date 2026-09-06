<?php

use App\Enums\SubjectType;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Support\SubjectDefinitions;
use Database\Seeders\ClassSeeder;
use Database\Seeders\ClassSubjectSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubjectSeeder;
use Database\Seeders\TeacherSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedClassSubjectSeederPrerequisites(): void
{
    test()->seed(RoleSeeder::class);
    test()->seed(UserSeeder::class);
    test()->seed(TeacherSeeder::class);
    test()->seed(ClassSeeder::class);
    test()->seed(SubjectSeeder::class);
}

it('attaches only primary-level subjects to primary classes', function () {
    seedClassSubjectSeederPrerequisites();
    test()->seed(ClassSubjectSeeder::class);

    $classOne = Classes::where('name', 'Class 1')->firstOrFail();
    $attachedNames = ClassGroupSubject::where('class_id', $classOne->id)->with('subject')->get()->pluck('subject.name');

    $expectedNames = collect(SubjectDefinitions::primary())->pluck('name');

    expect($attachedNames->sort()->values()->all())->toBe($expectedNames->sort()->values()->all())
        // Secondary/college-only subjects must never reach a primary class.
        ->and($attachedNames)->not->toContain('Physics')
        ->and($attachedNames)->not->toContain('Accounting');
});

it('attaches only secondary-level subjects to secondary classes', function () {
    seedClassSubjectSeederPrerequisites();
    test()->seed(ClassSubjectSeeder::class);

    $classSix = Classes::where('name', 'Class 6')->firstOrFail();
    $attachedNames = ClassGroupSubject::where('class_id', $classSix->id)->with('subject')->get()->pluck('subject.name');

    $expectedNames = collect(SubjectDefinitions::secondary())->pluck('name');

    expect($attachedNames->sort()->values()->all())->toBe($expectedNames->sort()->values()->all())
        // College-only subjects must never reach a secondary class.
        ->and($attachedNames)->not->toContain('Logic');
});

it('attaches every subject as compulsory with no group restriction', function () {
    seedClassSubjectSeederPrerequisites();
    test()->seed(ClassSubjectSeeder::class);

    $classOne = Classes::where('name', 'Class 1')->firstOrFail();
    $pivots = ClassGroupSubject::where('class_id', $classOne->id)->get();

    expect($pivots->every(fn (ClassGroupSubject $pivot) => $pivot->subject_type === SubjectType::Compulsory))->toBeTrue()
        ->and($pivots->every(fn (ClassGroupSubject $pivot) => $pivot->group_id === null))->toBeTrue();
});

it('is idempotent — running the seeder twice creates no extra rows', function () {
    seedClassSubjectSeederPrerequisites();
    test()->seed(ClassSubjectSeeder::class);

    $countAfterFirstRun = ClassGroupSubject::count();

    test()->seed(ClassSubjectSeeder::class);

    expect(ClassGroupSubject::count())->toBe($countAfterFirstRun);
});
