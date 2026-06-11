<?php

namespace Database\Factories;

use App\Enums\PublicHolidayType;
use App\Models\PublicHoliday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicHoliday>
 */
class PublicHolidayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('first day of january this year', 'last day of december this year');

        return [
            'name' => $this->faker->words(2, true),
            'type' => PublicHolidayType::Single,
            'start_date' => $startDate,
            'end_date' => null,
            'is_recurring' => false,
            'description' => null,
        ];
    }

    public function range(): static
    {
        return $this->state(function () {
            $startDate = $this->faker->dateTimeBetween('first day of january this year', 'last day of november this year');
            $endDate = $this->faker->dateTimeBetween($startDate, (clone $startDate)->modify('+7 days'));

            return [
                'type' => PublicHolidayType::Range,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
        });
    }
}
