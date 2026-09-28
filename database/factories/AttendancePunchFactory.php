<?php

namespace Database\Factories;

use App\Models\AttendanceDevice;
use App\Models\AttendancePunch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendancePunch>
 */
class AttendancePunchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_device_id' => AttendanceDevice::factory(),
            'enroll_id' => (string) fake()->numberBetween(1, 99999),
            'punched_at' => now(),
            'verify_type' => 1,
            'state' => 0,
            'processed_at' => null,
        ];
    }
}
