<?php

namespace App\Actions;

use App\Enums\ActingAdminLevel;
use App\Models\ActingAdmin;
use App\Models\User;
use Spatie\Permission\Models\Role;

class SyncActingAdminRolesAction
{
    /**
     * Recomputes both acting-admin roles from the user's full set of records,
     * rather than diffing one record — a user can hold an active record at
     * each level (or none), and this stays correct regardless of which record
     * changed or which day it is.
     */
    public function handle(User $user): void
    {
        $activeLevels = ActingAdmin::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('from_date', '<=', today())
            ->where(function ($query) {
                $query->whereNull('to_date')->orWhere('to_date', '>=', today());
            })
            ->get()
            ->map(fn (ActingAdmin $record) => $record->level->value)
            ->unique();

        foreach (ActingAdminLevel::cases() as $level) {
            $role = Role::firstOrCreate(['name' => $level->value, 'guard_name' => 'web']);

            if ($activeLevels->contains($level->value)) {
                $user->assignRole($role);
            } else {
                $user->removeRole($role);
            }
        }
    }
}
