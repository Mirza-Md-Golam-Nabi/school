<?php

namespace Database\Seeders;

use App\Enums\FeeDiscountType;
use App\Models\FeeDiscount;
use Illuminate\Database\Seeder;

class FeeDiscountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([25, 50, 75, 100] as $percent) {
            FeeDiscount::firstOrCreate(
                ['name' => "Scholarship {$percent}%"],
                [
                    'discount_type' => FeeDiscountType::Percent,
                    'discount_value' => $percent,
                ]
            );
        }

        FeeDiscount::firstOrCreate(
            ['name' => 'Poor Fund'],
            [
                'discount_type' => FeeDiscountType::Percent,
                'discount_value' => 20,
            ]
        );
    }
}
