<?php

namespace App\Observers;

use App\Actions\Attendance\ApplyProfileStatusToEnrollmentsAction;
use App\Actions\Attendance\EnrollPersonOnDevicesAction;
use Illuminate\Database\Eloquent\Model;

/**
 * Observes StudentProfile, TeacherProfile and StaffProfile so attendance devices
 * follow the school's people automatically: new people get an enroll ID, and people
 * who leave are scheduled for removal from the device.
 */
class DeviceEnrollmentObserver
{
    public function __construct(
        private readonly EnrollPersonOnDevicesAction $enroll,
        private readonly ApplyProfileStatusToEnrollmentsAction $applyStatus,
    ) {}

    public function created(Model $person): void
    {
        $this->enroll->handle($person);
    }

    public function updated(Model $person): void
    {
        if (! $person->wasChanged('status')) {
            return;
        }

        $this->applyStatus->handle($person);
        $this->enroll->handle($person);
    }

    public function deleted(Model $person): void
    {
        // A permanent delete also fires "deleted"; forceDeleted() below handles it.
        if (method_exists($person, 'isForceDeleting') && $person->isForceDeleting()) {
            return;
        }

        $this->applyStatus->handle($person);
    }

    public function restored(Model $person): void
    {
        $this->applyStatus->handle($person);
        $this->enroll->handle($person);
    }

    public function forceDeleted(Model $person): void
    {
        $this->applyStatus->handle($person, permanentlyDeleted: true);
    }
}
