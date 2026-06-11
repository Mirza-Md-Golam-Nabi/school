<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffProfile>
 */
class StaffProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'nationality' => 'Bangladeshi',
            'status' => EmploymentStatus::Active->value,
        ];
    }
}
