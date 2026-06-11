<?php

namespace Database\Factories;

use App\Enums\LeaveApplicationStatus;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveApplication>
 */
class LeaveApplicationFactory extends Factory
{
    public function definition(): array
    {
        $fromDate = $this->faker->dateTimeBetween('now', '+1 month');
        $toDate = $this->faker->dateTimeBetween($fromDate, '+1 month');

        return [
            'applicant_type' => TeacherProfile::class,
            'applicant_id' => TeacherProfile::factory(),
            'leave_type_id' => LeaveType::factory(),
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'total_days' => $this->faker->numberBetween(1, 10),
            'reason' => $this->faker->sentence(),
            'status' => LeaveApplicationStatus::Pending,
            'applied_by' => User::factory(),
            'actioned_by' => null,
            'action_remarks' => null,
            'actioned_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => LeaveApplicationStatus::Approved,
            'actioned_by' => User::factory(),
            'actioned_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => LeaveApplicationStatus::Rejected,
            'actioned_by' => User::factory(),
            'actioned_at' => now(),
        ]);
    }
}
