<?php

namespace App\Observers;

use App\Actions\SyncActingAdminRolesAction;
use App\Models\ActingAdmin;

class ActingAdminObserver
{
    public function __construct(private SyncActingAdminRolesAction $syncRoles) {}

    public function created(ActingAdmin $actingAdmin): void
    {
        $this->syncRoles->handle($actingAdmin->user);
    }

    public function updated(ActingAdmin $actingAdmin): void
    {
        $this->syncRoles->handle($actingAdmin->user);
    }

    public function deleted(ActingAdmin $actingAdmin): void
    {
        $this->syncRoles->handle($actingAdmin->user);
    }
}
