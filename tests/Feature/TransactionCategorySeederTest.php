<?php

use App\Enums\TransactionType;
use App\Models\TransactionCategory;
use Database\Seeders\TransactionCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds exactly 5 transaction categories with a mix of income and expense types', function () {
    (new TransactionCategorySeeder)->run();

    expect(TransactionCategory::count())->toBe(5)
        ->and(TransactionCategory::where('type', TransactionType::Income)->count())->toBe(3)
        ->and(TransactionCategory::where('type', TransactionType::Expense)->count())->toBe(2)
        ->and(TransactionCategory::where('is_active', true)->count())->toBe(5);
});

it('does not duplicate categories when the seeder runs again', function () {
    (new TransactionCategorySeeder)->run();
    (new TransactionCategorySeeder)->run();

    expect(TransactionCategory::count())->toBe(5);
});
