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
            'reported_sizes' => $this->usedCounts($report, $device),
            'sizes_reported_at' => now(),
            'unknown_device_users' => $unknown,
        ])->save();

        return ['updated' => $updated, 'removed' => $removed, 'unknown' => count($unknown)];
    }

    /**
     * What the device is currently holding. Users, fingerprints and cards are counted
     * from the reported user list (more reliable than the device's own counters, which
     * differ between firmware versions); only the punch-record total comes from the
     * client. The capacity limits are entered by an admin, never taken from a report.
     *
     * @param  array<string, mixed>  $report
     * @return array{users: int, fingers: int, cards: int, records: int|null}
     */
    private function usedCounts(array $report, AttendanceDevice $device): array
    {
        $onDevice = collect($report['users'] ?? []);

        return [
            'users' => $onDevice->count(),
            'fingers' => (int) $onDevice->sum(fn (array $user): int => (int) ($user['fingerprint_count'] ?? 0)),
            'cards' => $onDevice->filter(fn (array $user): bool => $this->normalizeCard($user['card_number'] ?? null) !== null)->count(),
            'records' => $report['sizes']['records'] ?? ($device->reported_sizes['records'] ?? null),
        ];
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
