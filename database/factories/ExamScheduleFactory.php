<?php

namespace Database\Factories;

use App\Models\ExamSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSchedule>
 */
class ExamScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_date' => fake()->dateTimeBetween('now', '+2 weeks')->format('Y-m-d'),
        ];
    }
}
