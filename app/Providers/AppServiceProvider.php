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
        Gate::before(function ($user, $_ability) {
            if ($user->hasRole('super-admin')) {
                return true;
            }

            // Acting admin gets super-admin level access during active period
            $isActing = ActingAdmin::where('user_id', $user->id)
                ->where('is_active', true)
                ->where('from_date', '<=', today())
                ->where('to_date', '>=', today())
                ->exists();

            if ($isActing) {
                return true;
            }
        });

        Attendance::observe(AttendanceObserver::class);
        ActingAdmin::observe(ActingAdminObserver::class);
    }
}
