<?php

namespace Database\Factories;

use App\Models\ExamType;
use App\Models\ResultRule;
use App\Models\ResultRuleComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultRuleComponent>
 */
class ResultRuleComponentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'result_rule_id' => ResultRule::factory(),
            'exam_type_id' => fn (): int => ExamType::create(['name' => fake()->unique()->word(), 'is_active' => true])->id,
            'weight_percent' => 50,
            'must_pass' => false,
            'sort_order' => 0,
        ];
    }
}
