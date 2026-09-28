<?php

namespace App\Actions\Attendance;

use App\Enums\StudentStatus;
use App\Models\DeviceUser;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Model;

class ApplyProfileStatusToEnrollmentsAction
{
    /**
     * Keeps a person's device enrollments in step with their profile status.
     *
     * - Active again: a pending/scheduled removal is cancelled, and a removed
     *   enrollment is restored under the same enroll ID.
     * - Graduated or transferred students: removal is scheduled automatically (after
     *   the grace period).
     * - Everything else that ends someone's time here — dropped students, resigned,
     *   terminated or retired staff, soft-deleted profiles — waits for an admin's
     *   approval, since those are the statuses most often set by mistake or reversed.
     * - A permanently deleted profile is scheduled like a graduation: nobody is left
     *   to approve or undo it.
     */
    public function handle(Model $person, bool $permanentlyDeleted = false): void
    {
        $enrollments = $person->deviceEnrollments()->get();

        if ($enrollments->isEmpty()) {
            return;
        }

        if (! $permanentlyDeleted && DeviceUser::isActivePerson($person)) {
            $enrollments->each(fn (DeviceUser $enrollment) => $enrollment->isRemoved()
                ? $enrollment->restoreToRoster()
                : $enrollment->cancelRemoval());

            return;
        }

        $needsApproval = ! $permanentlyDeleted && $this->needsApproval($person);

        $enrollments->each(fn (DeviceUser $enrollment) => $enrollment->requestRemoval($needsApproval));
    }

    private function needsApproval(Model $person): bool
    {
        if (! $person instanceof StudentProfile || $person->trashed()) {
            return true;
        }

        return $person->status === StudentStatus::Dropped;
    }
}
