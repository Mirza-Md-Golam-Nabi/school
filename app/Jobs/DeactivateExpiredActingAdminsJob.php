<?php

namespace App\Jobs;

use App\Models\ActingAdmin;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeactivateExpiredActingAdminsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        // Per-model update (not a bulk query update) so ActingAdminObserver::updated()
        // fires and revokes the super_admin_acting role.
        ActingAdmin::query()
            ->where('is_active', true)
            ->where('to_date', '<', today())
            ->get()
            ->each(fn (ActingAdmin $actingAdmin) => $actingAdmin->update(['is_active' => false]));
    }
}
