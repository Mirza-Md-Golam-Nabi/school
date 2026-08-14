<?php

namespace App\Providers;

use App\Models\ActingAdmin;
use App\Models\Attendance;
use App\Observers\ActingAdminObserver;
use App\Observers\AttendanceObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Acting admins are intentionally NOT bypassed here — they get panel
        // access via the super_admin_acting role (see User::canAccessPanel())
        // and their actual permissions come from that role's synced
        // permissions (see ActingAdminRoleSeeder), so the boundary can be
        // tightened or loosened just by editing the seeder.
        Gate::before(function ($user, $_ability) {
            if ($user->hasRole('super-admin')) {
                return true;
            }
        });

        Attendance::observe(AttendanceObserver::class);
        ActingAdmin::observe(ActingAdminObserver::class);
    }
}
