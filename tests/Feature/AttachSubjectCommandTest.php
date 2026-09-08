<?php

use App\Enums\SubjectType;
use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Group;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('fails when no option is given', function () {
    $this->artisan('attach:subject')
        ->assertExitCode(1);
});

it('fails when the secondary classes have not been created yet', function () {
    $this->artisan('attach:subject', ['--secondary' => true])
        ->assertExitCode(1);

    expect(Group::count())->toBe(0);
});

it('fails when the class 6-8 classes have not been created yet', function () {
    $this->artisan('attach:subject', ['--class-6-8' => true])
        ->assertExitCode(1);

    expect(ClassGroupSubject::count())->toBe(0);
});

it('fails when the primary classes have not been created yet', function () {
    $this->artisan('attach:subject', ['--primary' => true])
        ->assertExitCode(1);

    expect(ClassGroupSubject::count())->toBe(0);
});

it('creates the science, commerce, and humanities groups and links them to class 9 and 10', function () {
    $this->artisan('create:class', ['--secondary' => true])->assertExitCode(0);
    $this->artisan('create:subject', ['--secondary' => true])->assertExitCode(0);

    $this->artisan('attach:subject', ['--secondary' => true])
        ->assertExitCode(0);

    expect(Group::pluck('name')->sort()->values()->all())->toBe(['Commerce', 'Humanities', 'Science']);

    $class9 = Classes::where('name', 'Class 9')->first();
    $class10 = Classes::where('name', 'Class 10')->first();

    expect($class9->groups()->count())->toBe(3)
        ->and($class10->groups()->count())->toBe(3);

    // Class 6-8 have no groups and must not be linked to any group.
    $class6 = Classes::where('name', 'Class 6')->first();
    expect($class6->groups()->count())->toBe(0);
});

it('attaches all-student compulsory and optional subjects with a null group', function () {
    $this->artisan('create:class', ['--secondary' => true])->assertExitCode(0);
    $this->artisan('create:subject', ['--secondary' => true])->assertExitCode(0);
    $this->artisan('attach:subject', ['--secondary' => true])->assertExitCode(0);

    $class9 = Classes::where('name', 'Class 9')->first();

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

it('attaches group-specific compulsory and optional subjects to the correct group', function () {
    $this->artisan('create:class', ['--secondary' => true])->assertExitCode(0);
    $this->artisan('create:subject', ['--secondary' => true])->assertExitCode(0);
    $this->artisan('attach:subject', ['--secondary' => true])->assertExitCode(0);

    $class9 = Classes::where('name', 'Class 9')->first();
    $science = Group::where('name', 'Science')->first();
    $commerce = Group::where('name', 'Commerce')->first();
    $humanities = Group::where('name', 'Humanities')->first();

    $biologyForScience = ClassGroupSubject::query()
        ->where('class_id', $class9->id)
        ->where('group_id', $science->id)
        ->whereHas('subject', fn ($query) => $query->where('name', 'Biology'))
        ->first();

    expect($biologyForScience->subject_type)->toBe(SubjectType::Optional);

    // General Science is compulsory for both Commerce and Humanities, as two distinct attachments.
    $generalScience = Subject::where('name', 'General Science')->first();

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

it('is idempotent — running the command twice creates no extra rows', function () {
    $this->artisan('create:class', ['--secondary' => true])->assertExitCode(0);
    $this->artisan('create:subject', ['--secondary' => true])->assertExitCode(0);

    $this->artisan('attach:subject', ['--secondary' => true])->assertExitCode(0);
    $groupCountAfterFirstRun = Group::count();
    $attachmentCountAfterFirstRun = ClassGroupSubject::count();

    $this->artisan('attach:subject', ['--secondary' => true])->assertExitCode(0);

    expect(Group::count())->toBe($groupCountAfterFirstRun)
        ->and(ClassGroupSubject::count())->toBe($attachmentCountAfterFirstRun);
});

it('skips a mapped subject that has not been created yet without failing', function () {
    $this->artisan('create:class', ['--secondary' => true])->assertExitCode(0);
    // Deliberately skip create:subject so no Subject rows exist yet.

    $this->artisan('attach:subject', ['--secondary' => true])
        ->assertExitCode(0);

    expect(ClassGroupSubject::count())->toBe(0)
        ->and(Group::count())->toBe(3);
});

it('attaches the class 6-8 compulsory subjects with no group, without creating any group', function () {
    $this->artisan('create:class', ['--secondary' => true])->assertExitCode(0);
    $this->artisan('create:subject', ['--secondary' => true])->assertExitCode(0);

    $this->artisan('attach:subject', ['--class-6-8' => true])
        ->assertExitCode(0);

    expect(Group::count())->toBe(0);

    foreach (['Class 6', 'Class 7', 'Class 8'] as $className) {
        $class = Classes::where('name', $className)->first();

        $agricultureAttachment = ClassGroupSubject::query()
            ->where('class_id', $class->id)
            ->whereNull('group_id')
            ->whereHas('subject', fn ($query) => $query->where('name', 'Agricultural Education'))
            ->first();

        expect($agricultureAttachment)->not->toBeNull()
            ->and($agricultureAttachment->subject_type)->toBe(SubjectType::Compulsory);
    }

    // Class 9 must not receive the class 6-8 mapping.
    $class9 = Classes::where('name', 'Class 9')->first();
    expect(ClassGroupSubject::where('class_id', $class9->id)->count())->toBe(0);
});

it('attaches both the secondary and class 6-8 mappings in a single run', function () {
    $this->artisan('create:class', ['--secondary' => true])->assertExitCode(0);
    $this->artisan('create:subject', ['--secondary' => true])->assertExitCode(0);

    $this->artisan('attach:subject', ['--secondary' => true, '--class-6-8' => true])
        ->assertExitCode(0);

    expect(Group::count())->toBe(3);

    $class6 = Classes::where('name', 'Class 6')->first();
    $class9 = Classes::where('name', 'Class 9')->first();

    expect(ClassGroupSubject::where('class_id', $class6->id)->whereNull('group_id')->exists())->toBeTrue()
        ->and(ClassGroupSubject::where('class_id', $class9->id)->exists())->toBeTrue();
});

it('attaches the primary compulsory subjects with no group, without creating any group', function () {
    $this->artisan('create:class', ['--primary' => true])->assertExitCode(0);
    $this->artisan('create:subject', ['--primary' => true])->assertExitCode(0);

    $this->artisan('attach:subject', ['--primary' => true])
        ->assertExitCode(0);

    expect(Group::count())->toBe(0);

    foreach (['Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5'] as $className) {
        $class = Classes::where('name', $className)->first();

        $banglaAttachment = ClassGroupSubject::query()
            ->where('class_id', $class->id)
            ->whereNull('group_id')
            ->whereHas('subject', fn ($query) => $query->where('name', 'Bangla'))
            ->first();

        expect($banglaAttachment)->not->toBeNull()
            ->and($banglaAttachment->subject_type)->toBe(SubjectType::Compulsory);
    }
});

it('attaches all three primary/6-8/secondary mappings together', function () {
    $this->artisan('create:class', ['--primary' => true, '--secondary' => true])->assertExitCode(0);
    $this->artisan('create:subject', ['--primary' => true, '--secondary' => true])->assertExitCode(0);

    $this->artisan('attach:subject', [
        '--primary' => true,
        '--class-6-8' => true,
        '--secondary' => true,
    ])->assertExitCode(0);

    expect(Group::count())->toBe(3);

    $class1 = Classes::where('name', 'Class 1')->first();
    $class6 = Classes::where('name', 'Class 6')->first();
    $class9 = Classes::where('name', 'Class 9')->first();

    expect(ClassGroupSubject::where('class_id', $class1->id)->whereNull('group_id')->exists())->toBeTrue()
        ->and(ClassGroupSubject::where('class_id', $class6->id)->whereNull('group_id')->exists())->toBeTrue()
        ->and(ClassGroupSubject::where('class_id', $class9->id)->exists())->toBeTrue();
});
