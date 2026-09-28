<?php

namespace App\Actions\Attendance;

use App\Enums\DeviceUserRemovalStatus;
use App\Models\AttendanceDevice;
use App\Models\DeviceUser;

class ApplyDeviceSyncReportAction
{
    /**
     * Records what the sync client found on the device: card numbers and fingerprint
     * counts of known people, deletions it carried out, the device's capacity, and any
     * users on the device this software doesn't know about (left untouched).
     *
     * @param  array{users?: array<int, array<string, mixed>>, removed?: array<int, string>, sizes?: array<string, int|null>|null}  $report
     * @return array{updated: int, removed: int, unknown: int}
     */
    public function handle(AttendanceDevice $device, array $report): array
    {
        $roster = $device->deviceUsers()->active()->get()->keyBy('enroll_id');
        $updated = 0;
        $unknown = [];

        foreach ($report['users'] ?? [] as $reported) {
            $enrollment = $roster->get((string) $reported['enroll_id']);

            if (! $enrollment) {
                $unknown[] = [
                    'enroll_id' => (string) $reported['enroll_id'],
                    'name' => $reported['name'] ?? null,
                    'card_number' => $this->normalizeCard($reported['card_number'] ?? null),
                ];

                continue;
            }

            $enrollment->forceFill([
                'card_number' => $this->normalizeCard($reported['card_number'] ?? null),
                'fingerprint_count' => (int) ($reported['fingerprint_count'] ?? 0),
                'last_seen_on_device_at' => now(),
            ])->save();

            $updated++;
        }

        $removed = 0;

        foreach ($report['removed'] ?? [] as $enrollId) {
            $enrollment = $roster->get((string) $enrollId);

            // Only honour deletions that were actually scheduled.
            if ($enrollment?->removal_status === DeviceUserRemovalStatus::Queued) {
                $enrollment->markRemoved();
                $removed++;
            }
        }

        $device->forceFill([
            'reported_sizes' => $report['sizes'] ?? $device->reported_sizes,
            'sizes_reported_at' => now(),
            'unknown_device_users' => $unknown,
        ])->save();

        return ['updated' => $updated, 'removed' => $removed, 'unknown' => count($unknown)];
    }

    /**
     * Devices report "0" for a user with no card.
     */
    private function normalizeCard(mixed $card): ?string
    {
        $card = trim((string) $card);

        return $card === '' || $card === '0' ? null : $card;
    }
}
