<?php

namespace Database\Factories;

use App\Models\GradeScale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeScale>
 */
class GradeScaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'letter_grade' => fake()->randomElement(['A+', 'A', 'A-', 'B', 'C', 'D', 'F']),
            'min_mark' => fake()->numberBetween(0, 90),
            'max_mark' => fake()->numberBetween(91, 100),
            'grade_point' => fake()->randomFloat(2, 0, 5),
            'color' => fake()->randomElement(['success', 'info', 'warning', 'danger', 'gray']),
        ];
    }
}
