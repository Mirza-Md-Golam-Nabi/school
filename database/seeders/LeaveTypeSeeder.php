<?php

namespace Database\Seeders;

use App\Enums\LeaveApplicability;
use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /** @var array<string, array{allowed_days: int, applicable_gender: LeaveApplicability}> */
    private array $leaveTypes = [
        'Casual Leave' => ['allowed_days' => 10, 'applicable_gender' => LeaveApplicability::All],
        'Sick Leave' => ['allowed_days' => 14, 'applicable_gender' => LeaveApplicability::All],
        'Maternity Leave' => ['allowed_days' => 180, 'applicable_gender' => LeaveApplicability::Female],
        'Paternity Leave' => ['allowed_days' => 7, 'applicable_gender' => LeaveApplicability::Male],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->leaveTypes as $name => $config) {
            LeaveType::firstOrCreate(
                ['name' => $name],
                [
                    'allowed_days_per_year' => $config['allowed_days'],
                    'applicable_gender' => $config['applicable_gender'],
                    'is_active' => true,
                ]
            );
        }
    }
}
