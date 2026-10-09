<?php

namespace App\Providers;

use App\Models\ActingAdmin;
use App\Models\Attendance;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Observers\ActingAdminObserver;
use App\Observers\AttendanceObserver;
use App\Observers\DeviceEnrollmentObserver;
use App\Support\ActivityLogLabelCache;
use App\Support\SchoolSettingStore;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ActivityLogLabelCache::class);
        $this->app->scoped(SchoolSettingStore::class);
    }

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

        StudentProfile::observe(DeviceEnrollmentObserver::class);
        TeacherProfile::observe(DeviceEnrollmentObserver::class);
        StaffProfile::observe(DeviceEnrollmentObserver::class);

        // A renamed/removed record must not keep being logged under its old
        // label for the rest of the request — see ActivityLogLabelCache.
        foreach (ActivityLogLabelCache::SOURCE_MODELS as $sourceModel) {
            Event::listen(
                ["eloquent.updated: {$sourceModel}", "eloquent.deleted: {$sourceModel}", "eloquent.restored: {$sourceModel}"],
                fn () => $this->app->make(ActivityLogLabelCache::class)->flush(),
            );
        }

        // Login/Logout/Failed-login activity logging is handled by
        // App\Listeners\Log*Login/Logout, auto-discovered from app/Listeners
        // — do not also register them here, or events fire twice.
    }
}
