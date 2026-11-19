<?php

namespace Database\Seeders;

use App\Enums\SmsVendorDriver;
use App\Models\SmsVendorResponseCode;
use Illuminate\Database\Seeder;

class SmsVendorResponseCodeSeeder extends Seeder
{
    /**
     * Response codes published by each SMS provider. Safe to run again —
     * existing codes are updated in place.
     *
     * @var array<string, array<string, string>>
     */
    private const CODES = [
        'bulksmsbd' => [
            '202' => 'SMS Submitted Successfully',
            '1001' => 'Invalid Number',
            '1002' => 'sender id not correct/sender id is disabled',
            '1003' => 'Please Required all fields/Contact Your System Administrator',
            '1005' => 'Internal Error',
            '1006' => 'Balance Validity Not Available',
            '1007' => 'Balance Insufficient',
            '1011' => 'User Id not found',
            '1012' => 'Masking SMS must be sent in Bengali',
            '1013' => 'Sender Id has not found Gateway by api key',
            '1014' => 'Sender Type Name not found using this sender by api key',
            '1015' => 'Sender Id has not found Any Valid Gateway by api key',
            '1016' => 'Sender Type Name Active Price Info not found by this sender id',
            '1017' => 'Sender Type Name Price Info not found by this sender id',
            '1018' => 'The Owner of this (username) Account is disabled',
            '1019' => 'The (sender type name) Price of this (username) Account is disabled',
            '1020' => 'The parent of this account is not found.',
            '1021' => 'The parent active (sender type name) price of this account is not found.',
            '1031' => 'Your Account Not Verified, Please Contact Administrator.',
            '1032' => 'ip Not whitelisted',
        ],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const SUCCESS_CODES = [
        'bulksmsbd' => ['202'],
    ];

    public function run(): void
    {
        foreach (self::CODES as $driver => $codes) {
            foreach ($codes as $code => $message) {
                SmsVendorResponseCode::updateOrCreate(
                    ['driver' => SmsVendorDriver::from($driver), 'code' => (string) $code],
                    ['message' => $message, 'is_success' => in_array((string) $code, self::SUCCESS_CODES[$driver], true)],
                );
            }
        }
    }
}
