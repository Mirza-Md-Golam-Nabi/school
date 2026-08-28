<?php

use App\Enums\SalaryComponentType;
use App\Models\SalaryComponent;
use Database\Seeders\SalaryComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds exactly 4 salary components with the expected types', function () {
    (new SalaryComponentSeeder)->run();

    expect(SalaryComponent::count())->toBe(4)
        ->and(SalaryComponent::where('type', SalaryComponentType::Allowance)->count())->toBe(3)
        ->and(SalaryComponent::where('type', SalaryComponentType::Deduction)->count())->toBe(1)
        ->and(SalaryComponent::where('is_active', true)->count())->toBe(4);
});

it('does not duplicate components when the seeder runs again', function () {
    (new SalaryComponentSeeder)->run();
    (new SalaryComponentSeeder)->run();

    expect(SalaryComponent::count())->toBe(4);
});
