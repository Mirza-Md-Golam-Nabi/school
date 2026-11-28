<?php

namespace App\Jobs;

use App\Actions\SyncActingAdminRolesAction;
use App\Models\ActingAdmin;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Daily acting-admin housekeeping: switches off records whose to_date has
 * passed, and hands the role to records whose from_date has just arrived
 * (saving a future-dated record grants nothing until that day).
 */
class DeactivateExpiredActingAdminsJob implements ShouldQueue
{
    use Queueable;

    public function handle(SyncActingAdminRolesAction $syncRoles): void
    {
        // Per-model update (not a bulk query update) so ActingAdminObserver::updated()
        // fires and revokes the acting role.
        ActingAdmin::query()
            ->where('is_active', true)
            ->where('to_date', '<', today())
            ->get()
            ->each(fn (ActingAdmin $actingAdmin) => $actingAdmin->update(['is_active' => false]));

        User::query()
            ->whereHas('actingAdmins', fn ($query) => $query
                ->where('is_active', true)
                ->where('from_date', '<=', today()))
            ->get()
            ->each(fn (User $user) => $syncRoles->handle($user));
    }
}
