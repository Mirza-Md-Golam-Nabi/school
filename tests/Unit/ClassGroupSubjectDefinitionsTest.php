<?php

use App\Enums\SubjectType;
use App\Support\ClassGroupSubjectDefinitions;

it('lists the science, commerce, and humanities groups', function () {
    expect(ClassGroupSubjectDefinitions::SECONDARY_GROUPS)->toBe(['Science', 'Commerce', 'Humanities']);
});

it('marks the all-student compulsory and optional subjects with a null group', function () {
    $entries = collect(ClassGroupSubjectDefinitions::secondary())->whereNull('group');

    expect($entries)->toHaveCount(2);

    $compulsory = $entries->firstWhere('subject_type', SubjectType::Compulsory);
    $optional = $entries->firstWhere('subject_type', SubjectType::Optional);

    expect($compulsory['subjects'])->toContain('Bangla 1st Paper', 'English 1st Paper', 'Information & Communication Technology')
        ->and($optional['subjects'])->toContain('Agricultural Education', 'Home Science', 'Economics');
});

it('assigns compulsory and optional subjects to the science group', function () {
    $entries = collect(ClassGroupSubjectDefinitions::secondary())->where('group', 'Science');

    $compulsory = $entries->firstWhere('subject_type', SubjectType::Compulsory);
    $optional = $entries->firstWhere('subject_type', SubjectType::Optional);

    expect($compulsory['subjects'])->toBe(['Physics', 'Chemistry', 'Bangladesh and Global Studies'])
        ->and($optional['subjects'])->toBe(['Biology', 'Higher Mathematics']);
});

it('shares General Science between the commerce and humanities groups', function () {
    $entries = collect(ClassGroupSubjectDefinitions::secondary());

    $commerce = $entries->firstWhere('group', 'Commerce');
    $humanities = $entries->firstWhere('group', 'Humanities');

    expect($commerce['subjects'])->toContain('General Science')
        ->and($humanities['subjects'])->toContain('General Science')
        ->and($commerce['subject_type'])->toBe(SubjectType::Compulsory)
        ->and($humanities['subject_type'])->toBe(SubjectType::Compulsory);
});

it('lists the class 6-8 compulsory subjects with a null group', function () {
    $entries = collect(ClassGroupSubjectDefinitions::classSixToEight());

    expect($entries)->toHaveCount(1);

    $entry = $entries->first();

    expect($entry['group'])->toBeNull()
        ->and($entry['subject_type'])->toBe(SubjectType::Compulsory)
        ->and($entry['subjects'])->toBe([
            'Bangla 1st Paper',
            'Bangla 2nd Paper',
            'English 1st Paper',
            'English 2nd Paper',
            'Mathematics',
            'Religious Studies',
            'Information & Communication Technology',
            'General Science',
            'Agricultural Education',
        ]);
});

it('lists the primary level compulsory subjects with a null group', function () {
    $entries = collect(ClassGroupSubjectDefinitions::primary());

    expect($entries)->toHaveCount(1);

    $entry = $entries->first();

    expect($entry['group'])->toBeNull()
        ->and($entry['subject_type'])->toBe(SubjectType::Compulsory)
        ->and($entry['subjects'])->toBe([
            'Bangla',
            'English',
            'Mathematics',
            'Bangladesh and Global Studies',
            'Science',
            'Religious Studies',
        ]);
});
