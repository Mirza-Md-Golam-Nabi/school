<?php

namespace Database\Factories;

use App\Models\ActingAdmin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActingAdmin>
 */
class ActingAdminFactory extends Factory
{
    public function definition(): array
    {
        $fromDate = $this->faker->dateTimeBetween('now', '+7 days');
        $toDate = $this->faker->dateTimeBetween($fromDate, '+30 days');

        return [
            'user_id' => User::factory(),
            'assigned_by' => User::factory(),
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'is_active' => true,
            'remarks' => $this->faker->optional()->sentence(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
            'from_date' => today(),
            'to_date' => today()->addDays(7),
        ]);
    }
}
