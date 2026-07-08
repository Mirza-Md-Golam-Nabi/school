<?php

namespace Database\Seeders;

use App\Enums\PublicHolidayType;
use App\Models\PublicHoliday;
use Illuminate\Database\Seeder;

class PublicHolidaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $year = (int) now()->year;

        $singleDayHolidays = [
            ['name' => 'International Mother Language Day', 'date' => "{$year}-02-21"],
            ['name' => 'Independence Day', 'date' => "{$year}-03-26"],
            ['name' => 'Bengali New Year', 'date' => "{$year}-04-14"],
            ['name' => 'May Day', 'date' => "{$year}-05-01"],
            ['name' => 'National Mourning Day', 'date' => "{$year}-08-15"],
            ['name' => 'Victory Day', 'date' => "{$year}-12-16"],
            ['name' => 'Christmas Day', 'date' => "{$year}-12-25"],
        ];

        foreach ($singleDayHolidays as $holiday) {
            PublicHoliday::firstOrCreate(
                ['name' => $holiday['name'], 'start_date' => $holiday['date']],
                [
                    'type' => PublicHolidayType::Single,
                    'end_date' => null,
                    'is_recurring' => true,
                ]
            );
        }

        $rangeHolidays = [
            [
                'name' => 'Eid-ul-Fitr Vacation',
                'start' => "{$year}-04-10",
                'end' => "{$year}-04-12",
                'is_recurring' => false,
            ],
            [
                'name' => 'Eid-ul-Adha Vacation',
                'start' => "{$year}-06-17",
                'end' => "{$year}-06-19",
                'is_recurring' => false,
            ],
            [
                'name' => 'Winter Vacation',
                'start' => "{$year}-12-26",
                'end' => "{$year}-12-31",
                'is_recurring' => true,
            ],
        ];

        foreach ($rangeHolidays as $holiday) {
            PublicHoliday::firstOrCreate(
                ['name' => $holiday['name'], 'start_date' => $holiday['start']],
                [
                    'type' => PublicHolidayType::Range,
                    'end_date' => $holiday['end'],
                    'is_recurring' => $holiday['is_recurring'],
                ]
            );
        }
    }
}
