<?php

use App\Enums\UserType;
use App\Filament\Pages\GradeScaleSettings;
use App\Models\GradeScale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('loads the seeded default grading scale into the repeater', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    Livewire::test(GradeScaleSettings::class)
        ->assertSchemaStateSet(function (array $state) {
            expect($state['scales'])->toHaveCount(GradeScale::count());
        });
});

it('adds a new grade via the repeater and saves it', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $existing = GradeScale::query()->orderByDesc('min_mark')->get()->map(fn (GradeScale $scale): array => [
        'letter_grade' => $scale->letter_grade,
        'min_mark' => $scale->min_mark,
        'max_mark' => $scale->max_mark,
        'grade_point' => $scale->grade_point,
        'color' => $scale->color,
    ])->all();

    $existing[] = [
        'letter_grade' => 'A++',
        'min_mark' => 95,
        'max_mark' => 100,
        'grade_point' => 5.5,
        'color' => 'success',
    ];

    Livewire::test(GradeScaleSettings::class)
        ->fillForm(['scales' => $existing])
        ->call('save')
        ->assertNotified();

    expect(GradeScale::where('letter_grade', 'A++')->exists())->toBeTrue();
});

it('rejects a grade row where min mark exceeds max mark', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $countBefore = GradeScale::count();

    Livewire::test(GradeScaleSettings::class)
        ->fillForm([
            'scales' => [
                ['letter_grade' => 'Bad', 'min_mark' => 90, 'max_mark' => 10, 'grade_point' => 1.0, 'color' => 'gray'],
            ],
        ])
        ->call('save')
        ->assertNotified();

    expect(GradeScale::count())->toBe($countBefore);
});
