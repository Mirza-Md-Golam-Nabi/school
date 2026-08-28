<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\SalaryStructure;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class SalaryStructureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Every active teacher and staff member gets a flat-amount salary
     * structure (৳10,000–15,000) rather than a component-wise breakdown.
     */
    public function run(): void
    {
        $createdBy = User::where('user_type', UserType::Admin)->value('id')
            ?? User::query()->value('id');

        $profiles = TeacherProfile::with('user')->active()->get()
            ->concat(StaffProfile::with('user')->active()->get());

        foreach ($profiles as $profile) {
            if (! $profile->user) {
                continue;
            }

            $hasOpenStructure = SalaryStructure::where('profileable_type', $profile::class)
                ->where('profileable_id', $profile->id)
                ->whereNull('effective_to')
                ->exists();

            if ($hasOpenStructure) {
                continue;
            }

            SalaryStructure::create([
                'profileable_type' => $profile::class,
                'profileable_id' => $profile->id,
                'use_components' => false,
                'flat_amount' => (fake()->numberBetween(50, 70)) * 100,
                'effective_from' => $profile->joining_date ?? now()->startOfYear()->toDateString(),
                'created_by' => $createdBy,
            ]);
        }
    }
}
