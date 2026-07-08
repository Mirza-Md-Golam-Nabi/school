<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\ActingAdmin;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ActingAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Picks 2 teachers and creates 4-5 short (1-3 day) acting-admin
     * assignments for them, all inactive, one per distinct month from
     * January through the current month.
     */
    public function run(): void
    {
        $teacherUserIds = TeacherProfile::active()->with('user')->get()
            ->pluck('user')
            ->filter()
            ->pluck('id')
            ->values();

        if ($teacherUserIds->count() < 2) {
            return;
        }

        $selectedTeacherIds = $teacherUserIds->random(2);

        $assignedBy = User::where('user_type', UserType::Admin)->value('id')
            ?? User::query()->value('id');

        $year = (int) now()->year;
        $today = Carbon::today();
        $currentMonth = (int) now()->month;

        $recordCount = min(fake()->numberBetween(4, 5), $currentMonth);

        $months = collect(range(1, $currentMonth))->shuffle()->take($recordCount)->sort()->values();

        foreach ($months as $month) {
            $userId = $selectedTeacherIds->random();

            $monthStart = Carbon::create($year, $month, 1)->startOfDay();
            $monthEnd = $monthStart->copy()->endOfMonth();

            if ($monthEnd->greaterThan($today)) {
                $monthEnd = $today->copy();
            }

            $dayOffset = fake()->numberBetween(0, $monthStart->diffInDays($monthEnd));
            $fromDate = $monthStart->copy()->addDays($dayOffset);

            $maxSpan = min(2, (int) $fromDate->diffInDays($monthEnd));
            $toDate = $fromDate->copy()->addDays(fake()->numberBetween(0, $maxSpan));

            ActingAdmin::firstOrCreate(
                [
                    'user_id' => $userId,
                    'from_date' => $fromDate->toDateString(),
                    'to_date' => $toDate->toDateString(),
                ],
                [
                    'assigned_by' => $assignedBy,
                    'is_active' => false,
                    'remarks' => 'Acting admin while the principal was unavailable.',
                ]
            );
        }
    }
}
