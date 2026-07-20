<?php

namespace Database\Seeders\Helpers;

class LocationData
{
    /** @return array<int, string> */
    public static function districts(): array
    {
        return [
            'Dhaka', 'Gazipur', 'Narayanganj', 'Chattogram', 'Cumilla', "Cox's Bazar",
            'Sylhet', 'Rajshahi', 'Khulna', 'Barishal', 'Rangpur', 'Mymensingh',
            'Jessore', 'Bogura', 'Dinajpur', 'Faridpur', 'Noakhali', 'Tangail',
            'Pabna', 'Kishoreganj',
        ];
    }

    /** @return array<int, string> */
    public static function areas(): array
    {
        return [
            'Bagan Bari', 'Shanti Nagar', 'Model Town', 'New Colony', 'Puran Bazar',
            'Station Road', 'College Road', 'Jamtola', 'Bou Bazar', 'Uttor Para',
        ];
    }
}
