<?php

use App\Enums\SubjectType;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Group;
use App\Models\Subject;
use App\Support\ClassGroupSubjectDefinitions;
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

    $expectedNames = collect(ClassGroupSubjectDefinitions::primary())->flatMap(fn (array $entry) => $entry['subjects']);

    expect($attachedNames->sort()->values()->all())->toBe($expectedNames->sort()->values()->all())
        // Secondary/college-only subjects must never reach a primary class.
        ->and($attachedNames)->not->toContain('Physics')
        ->and($attachedNames)->not->toContain('Accounting');
});

it('attaches every primary subject as compulsory with no group restriction', function () {
    seedClassSubjectSeederPrerequisites();
    test()->seed(ClassSubjectSeeder::class);

    $classOne = Classes::where('name', 'Class 1')->firstOrFail();
    $pivots = ClassGroupSubject::where('class_id', $classOne->id)->get();

    expect($pivots->every(fn (ClassGroupSubject $pivot) => $pivot->subject_type === SubjectType::Compulsory))->toBeTrue()
        ->and($pivots->every(fn (ClassGroupSubject $pivot) => $pivot->group_id === null))->toBeTrue();
});

it('attaches only the class 6-8 mapping to class 6-8, not the full secondary subject list', function () {
    seedClassSubjectSeederPrerequisites();
    test()->seed(ClassSubjectSeeder::class);

    $classSix = Classes::where('name', 'Class 6')->firstOrFail();
    $attachedNames = ClassGroupSubject::where('class_id', $classSix->id)->with('subject')->get()->pluck('subject.name');

    $expectedNames = collect(ClassGroupSubjectDefinitions::classSixToEight())->flatMap(fn (array $entry) => $entry['subjects']);

    expect($attachedNames->sort()->values()->all())->toBe($expectedNames->sort()->values()->all())
        // Group-only subjects (Science/Commerce/Humanities picks) must never reach Class 6-8.
        ->and($attachedNames)->not->toContain('Physics')
        ->and($attachedNames)->not->toContain('Accounting')
        ->and($attachedNames)->not->toContain('Logic');

    expect(ClassGroupSubject::where('class_id', $classSix->id)->whereNull('group_id')->count())
        ->toBe(ClassGroupSubject::where('class_id', $classSix->id)->count());
});

it('creates the science, commerce, and humanities groups and links them to class 9 and 10 only', function () {
    seedClassSubjectSeederPrerequisites();
    test()->seed(ClassSubjectSeeder::class);

    expect(Group::pluck('name')->sort()->values()->all())->toBe(['Commerce', 'Humanities', 'Science']);

    $class9 = Classes::where('name', 'Class 9')->firstOrFail();
    $class10 = Classes::where('name', 'Class 10')->firstOrFail();

    expect($class9->groups()->count())->toBe(3)
        ->and($class10->groups()->count())->toBe(3);

    $classSix = Classes::where('name', 'Class 6')->firstOrFail();
    expect($classSix->groups()->count())->toBe(0);
});

it('attaches class 9-10 all-group subjects with a null group and correct compulsory/optional type', function () {
    seedClassSubjectSeederPrerequisites();
    test()->seed(ClassSubjectSeeder::class);

    $class9 = Classes::where('name', 'Class 9')->firstOrFail();

    $ictAttachment = ClassGroupSubject::query()
        ->where('class_id', $class9->id)
        ->whereNull('group_id')
        ->whereHas('subject', fn ($query) => $query->where('name', 'Information & Communication Technology'))
        ->first();

    expect($ictAttachment)->not->toBeNull()
        ->and($ictAttachment->subject_type)->toBe(SubjectType::Compulsory);

    $economicsAttachment = ClassGroupSubject::query()
        ->where('class_id', $class9->id)
        ->whereNull('group_id')
        ->whereHas('subject', fn ($query) => $query->where('name', 'Economics'))
        ->first();

    expect($economicsAttachment)->not->toBeNull()
        ->and($economicsAttachment->subject_type)->toBe(SubjectType::Optional);
});

it('attaches class 9-10 group-specific subjects to the correct group only', function () {
    seedClassSubjectSeederPrerequisites();
    test()->seed(ClassSubjectSeeder::class);

    $class9 = Classes::where('name', 'Class 9')->firstOrFail();
    $science = Group::where('name', 'Science')->firstOrFail();
    $commerce = Group::where('name', 'Commerce')->firstOrFail();
    $humanities = Group::where('name', 'Humanities')->firstOrFail();

    $biologyForScience = ClassGroupSubject::query()
        ->where('class_id', $class9->id)
        ->where('group_id', $science->id)
        ->whereHas('subject', fn ($query) => $query->where('name', 'Biology'))
        ->first();

    expect($biologyForScience)->not->toBeNull()
        ->and($biologyForScience->subject_type)->toBe(SubjectType::Optional);

    // General Science is compulsory for both Commerce and Humanities, as two distinct attachments.
    $generalScience = Subject::where('name', 'General Science')->firstOrFail();

    $forCommerce = ClassGroupSubject::query()
        ->where('class_id', $class9->id)
        ->where('group_id', $commerce->id)
        ->where('subject_id', $generalScience->id)
        ->first();

    $forHumanities = ClassGroupSubject::query()
        ->where('class_id', $class9->id)
        ->where('group_id', $humanities->id)
        ->where('subject_id', $generalScience->id)
        ->first();

    expect($forCommerce)->not->toBeNull()
        ->and($forCommerce->subject_type)->toBe(SubjectType::Compulsory)
        ->and($forHumanities)->not->toBeNull()
        ->and($forHumanities->subject_type)->toBe(SubjectType::Compulsory);
});

it('is idempotent — running the seeder twice creates no extra rows', function () {
    seedClassSubjectSeederPrerequisites();
    test()->seed(ClassSubjectSeeder::class);

    $countAfterFirstRun = ClassGroupSubject::count();
    $groupCountAfterFirstRun = Group::count();

    test()->seed(ClassSubjectSeeder::class);

    expect(ClassGroupSubject::count())->toBe($countAfterFirstRun)
        ->and(Group::count())->toBe($groupCountAfterFirstRun);
});
