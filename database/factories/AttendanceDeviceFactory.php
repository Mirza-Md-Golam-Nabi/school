<?php

namespace Database\Factories;

use App\Enums\AttendanceDeviceDriver;
use App\Models\AttendanceDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AttendanceDevice>
 */
class AttendanceDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Main Gate K40',
            'serial_number' => strtoupper(Str::random(12)),
            'driver' => AttendanceDeviceDriver::ZkPull,
            'api_token_hash' => AttendanceDevice::hashToken(Str::random(48)),
            'is_active' => true,
            'last_synced_at' => null,
        ];
    }

    public function withToken(string $token): static
    {
        return $this->state(fn (): array => ['api_token_hash' => AttendanceDevice::hashToken($token)]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
