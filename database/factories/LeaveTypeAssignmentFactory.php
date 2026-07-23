<?php

namespace Database\Factories;

use App\Models\LeaveType;
use App\Models\LeaveTypeAssignment;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveTypeAssignment>
 */
class LeaveTypeAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'leave_type_id' => LeaveType::factory(),
            'assignable_type' => TeacherProfile::class,
            'assignable_id' => TeacherProfile::factory(),
            'assigned_by' => User::factory(),
            'assigned_at' => now(),
        ];
    }
}
