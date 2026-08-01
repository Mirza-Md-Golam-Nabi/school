<?php

namespace Database\Factories;

use App\Enums\LeaveApplicability;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Casual Leave', 'Sick Leave', 'Maternity Leave', 'Annual Leave']),
            'allowed_days_per_year' => $this->faker->numberBetween(5, 30),
            'applicable_gender' => LeaveApplicability::All,
            'is_active' => true,
        ];
    }
}
