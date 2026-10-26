<?php

namespace Database\Factories;

use App\Enums\ResultRounding;
use App\Models\ExamType;
use App\Models\ResultRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultRule>
 */
class ResultRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'target_exam_type_id' => fn (): int => ExamType::create(['name' => fake()->unique()->word(), 'is_active' => true])->id,
            'full_marks' => null,
            'rounding' => ResultRounding::Decimal,
        ];
    }
}
