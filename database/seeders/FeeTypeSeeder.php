<?php

namespace Database\Seeders;

use App\Models\ExamType;
use App\Models\FeeType;
use Illuminate\Database\Seeder;

class FeeTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        FeeType::firstOrCreate(
            ['name' => 'Tuition Fee'],
            ['is_monthly' => true, 'is_active' => true]
        );

        $examTypes = ExamType::whereHas('examTypeConfig')->get();

        foreach ($examTypes as $examType) {
            FeeType::firstOrCreate(
                ['name' => "Exam Fee - {$examType->name}"],
                ['is_monthly' => false, 'is_active' => true]
            );
        }
    }
}
