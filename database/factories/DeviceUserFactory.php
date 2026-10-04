<?php

namespace Database\Factories;

use App\Models\AttendanceDevice;
use App\Models\DeviceUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<DeviceUser>
 */
class DeviceUserFactory extends Factory
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
            'enroll_id' => (string) fake()->unique()->numberBetween(1, 99999),
        ];
    }

    public function forPerson(Model $person): static
    {
        return $this->state(fn (): array => [
            'enrollable_type' => $person->getMorphClass(),
            'enrollable_id' => $person->getKey(),
        ]);
    }
}
