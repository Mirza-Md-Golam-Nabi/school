<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SmsVendorSeeder extends Seeder
{
    /**
     * Adds every provider's response codes so SMS results read correctly
     * from day one. The vendors themselves (API key, Sender ID) come only
     * from the central app (NextFusion) — none is seeded here.
     */
    public function run(): void
    {
        $this->call(SmsVendorResponseCodeSeeder::class);
    }
}
