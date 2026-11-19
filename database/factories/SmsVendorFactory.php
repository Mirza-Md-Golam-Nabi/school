<?php

namespace Database\Factories;

use App\Enums\SmsVendorDriver;
use App\Models\SmsVendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsVendor>
 */
class SmsVendorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'BulkSMSBD',
            'driver' => SmsVendorDriver::BulkSmsBd,
            'api_key' => fake()->sha1(),
            'sender_id' => '8809617613451',
            'priority' => 1,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
