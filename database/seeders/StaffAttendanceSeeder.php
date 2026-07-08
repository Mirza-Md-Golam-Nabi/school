<?php

namespace Database\Seeders;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\UserType;
use App\Models\Attendance;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\WorkingDaysCalculator;
use Database\Seeders\Helpers\AttendanceSeedHelper;
use Illuminate\Database\Seeder;

class StaffAttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $staff = StaffProfile::active()->get();

        if ($staff->isEmpty()) {
            return;
        }

        AttendanceSeedHelper::ensureWeekendDaysConfigured();

        [$from, $to] = AttendanceSeedHelper::dateRange();

        $workingDays = (new WorkingDaysCalculator)->getWorkingDays($from, $to);

        if ($workingDays->isEmpty()) {
            return;
        }

        $markedBy = User::where('user_type', UserType::Admin)->value('id')
            ?? User::query()->value('id');

        $existingKeys = Attendance::query()
            ->where('attendable_type', StaffProfile::class)
            ->whereIn('attendable_id', $staff->pluck('id'))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['attendable_id', 'date'])
            ->map(fn (Attendance $attendance) => $attendance->attendable_id.'|'.$attendance->date->toDateString())
            ->flip();

        $now = now();
        $rows = [];

        foreach ($workingDays as $date) {
            foreach ($staff as $member) {
                $key = $member->id.'|'.$date->toDateString();

                if (isset($existingKeys[$key])) {
                    continue;
                }

                $status = fake()->boolean(90) ? AttendanceStatus::Present : AttendanceStatus::Absent;

                $rows[] = [
                    'attendable_type' => StaffProfile::class,
                    'attendable_id' => $member->id,
                    'date' => $date->toDateString(),
                    'status' => $status->value,
                    'source' => AttendanceSource::Manual->value,
                    'class_id' => null,
                    'marked_by' => $markedBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        collect($rows)->chunk(1000)->each(fn ($chunk) => Attendance::insert($chunk->all()));
    }
}
