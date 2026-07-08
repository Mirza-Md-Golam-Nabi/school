<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /** @var array<string, int> */
    private array $leaveTypes = [
        'Casual Leave' => 10,
        'Sick Leave' => 14,
        'Maternity Leave' => 180,
        'Paternity Leave' => 7,
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->leaveTypes as $name => $allowedDays) {
            LeaveType::firstOrCreate(
                ['name' => $name],
                ['allowed_days_per_year' => $allowedDays, 'is_active' => true]
            );
        }
    }
}
