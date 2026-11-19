<?php

namespace Database\Seeders;

use App\Enums\SmsVendorDriver;
use App\Models\SmsVendor;
use Illuminate\Database\Seeder;

class SmsVendorSeeder extends Seeder
{
    /**
     * Adds every provider's response codes, then the BulkSMSBD vendor with its
     * Sender ID, switched off until the
     * super-admin enters the API key. Safe to run again — an existing vendor
     * of this provider is left untouched.
     */
    public function run(): void
    {
        $this->call(SmsVendorResponseCodeSeeder::class);

        SmsVendor::firstOrCreate(
            ['driver' => SmsVendorDriver::BulkSmsBd],
            [
                'name' => 'BulkSMSBD',
                'sender_id' => '8809617613451',
                'priority' => 1,
                'is_active' => false,
            ],
        );
    }
}
