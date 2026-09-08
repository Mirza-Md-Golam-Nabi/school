<?php

use App\Support\SubjectDefinitions;

it('defines the primary level subjects', function () {
    $names = collect(SubjectDefinitions::primary())->pluck('name');

    expect($names)->toContain('Bangla', 'English', 'Mathematics', 'ICT')
        ->and($names)->not->toContain('Physics');
});

it('defines the secondary level subjects including science, humanities, and business studies optionals', function () {
    $names = collect(SubjectDefinitions::secondary())->pluck('name');

    expect($names)->toContain('Bangla 1st Paper', 'Bangla 2nd Paper', 'English 1st Paper', 'English 2nd Paper')
        ->and($names)->toContain('Physics', 'Chemistry', 'Biology', 'Higher Mathematics')
        ->and($names)->toContain('History of Bangladesh and World Civilization', 'Economics')
        ->and($names)->toContain('Accounting', 'Business Entrepreneurship', 'Finance and Banking');
});

it('marks English papers as written-only and ICT as MCQ + practical only for the secondary level', function () {
    $subjects = collect(SubjectDefinitions::secondary())->keyBy('name');

    expect($subjects['English 1st Paper']['has_mcq'])->toBeFalse()
        ->and($subjects['English 2nd Paper']['has_mcq'])->toBeFalse()
        ->and($subjects['Information & Communication Technology']['has_written'])->toBeFalse()
        ->and($subjects['Information & Communication Technology']['has_practical'])->toBeTrue();
});

it('defines the college level subjects including science, humanities, and business studies groups', function () {
    $names = collect(SubjectDefinitions::college())->pluck('name');

    expect($names)->toContain('Bangla', 'English', 'ICT')
        ->and($names)->toContain('Physics', 'Chemistry', 'Biology', 'Higher Mathematics')
        ->and($names)->toContain('Logic', 'Sociology', 'Social Work')
        ->and($names)->toContain('Accounting', 'Finance, Banking and Insurance');
});

it('shares the same science-group subject names between secondary and college so they resolve to one record', function () {
    $secondary = collect(SubjectDefinitions::secondary())->pluck('name');
    $college = collect(SubjectDefinitions::college())->pluck('name');

    foreach (['Physics', 'Chemistry', 'Biology', 'Higher Mathematics'] as $shared) {
        expect($secondary)->toContain($shared)
            ->and($college)->toContain($shared);
    }
});

it('every subject definition has a unique code within its own level', function () {
    foreach ([SubjectDefinitions::primary(), SubjectDefinitions::secondary(), SubjectDefinitions::college()] as $subjects) {
        $codes = collect($subjects)->pluck('code');

        expect($codes->unique())->toHaveCount($codes->count());
    }
});
