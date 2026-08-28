<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Models\TransactionCategory;
use Illuminate\Database\Seeder;

class TransactionCategorySeeder extends Seeder
{
    /** @var array<string, TransactionType> */
    private array $categories = [
        'Donation' => TransactionType::Income,
        'Government Grant' => TransactionType::Income,
        'Development' => TransactionType::Income,
        'Maintenance' => TransactionType::Expense,
        'Utility Bill' => TransactionType::Expense,
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->categories as $name => $type) {
            TransactionCategory::firstOrCreate(
                ['name' => $name],
                ['type' => $type, 'is_active' => true]
            );
        }
    }
}
