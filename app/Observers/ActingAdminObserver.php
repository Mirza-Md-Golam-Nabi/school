<?php

namespace App\Observers;

use App\Models\ActingAdmin;

class ActingAdminObserver
{
    public function created(ActingAdmin $actingAdmin): void
    {
        if ($actingAdmin->isCurrentlyActive()) {
            $actingAdmin->user->assignRole('super_admin_acting');
        }
    }

    public function updated(ActingAdmin $actingAdmin): void
    {
        $user = $actingAdmin->user;

        if ($actingAdmin->isCurrentlyActive()) {
            $user->assignRole('super_admin_acting');
        } else {
            // Period ended or deactivated — revoke unless covered by another active record
            $hasOtherActive = ActingAdmin::where('user_id', $user->id)
                ->where('id', '!=', $actingAdmin->id)
                ->where('is_active', true)
                ->where('from_date', '<=', today())
                ->where('to_date', '>=', today())
                ->exists();

            if (! $hasOtherActive) {
                $user->removeRole('super_admin_acting');
            }
        }
    }

    public function deleted(ActingAdmin $actingAdmin): void
    {
        $user = $actingAdmin->user;

        $hasOtherActive = ActingAdmin::where('user_id', $user->id)
            ->where('id', '!=', $actingAdmin->id)
            ->where('is_active', true)
            ->where('from_date', '<=', today())
            ->where('to_date', '>=', today())
            ->exists();

        if (! $hasOtherActive) {
            $user->removeRole('super_admin_acting');
        }
    }
}
