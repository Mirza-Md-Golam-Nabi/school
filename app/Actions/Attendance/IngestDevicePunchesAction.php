<?php

namespace App\Actions\Attendance;

use App\Models\AttendanceDevice;
use App\Models\AttendancePunch;
use Illuminate\Support\Carbon;

class IngestDevicePunchesAction
{
    public function __construct(
        private readonly ProcessAttendancePunchesAction $processPunches,
    ) {}

    /**
     * Stores raw punches from any source (laptop sync, live capture, a future ADMS
     * push) and turns them into attendance. Re-sending a punch is harmless: the
     * unique index silently drops duplicates, so pull and live capture can overlap.
     *
     * @param  array<int, array{enroll_id: string, punched_at: string, verify_type?: int|null, state?: int|null}>  $punches
     * @return array{received: int, created: int}
     */
    public function handle(AttendanceDevice $device, array $punches): array
    {
        $now = now();

        $rows = collect($punches)
            ->map(fn (array $punch): array => [
                'attendance_device_id' => $device->id,
                'enroll_id' => (string) $punch['enroll_id'],
                'punched_at' => Carbon::parse($punch['punched_at'])
                    ->setTimezone(config('app.timezone'))
                    ->format('Y-m-d H:i:s'),
                'verify_type' => $punch['verify_type'] ?? null,
                'state' => $punch['state'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->unique(fn (array $row): string => $row['enroll_id'].'|'.$row['punched_at'])
            ->values()
            ->all();

        $created = AttendancePunch::query()->insertOrIgnore($rows);

        $device->forceFill(['last_synced_at' => $now])->save();

        $this->processPunches->handle($device);

        return [
            'received' => count($punches),
            'created' => $created,
        ];
    }
}
