<?php

use App\Models\SchoolAccount;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StaffSeeder;
use Database\Seeders\TeacherSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults every teacher and staff member\'s salary account to Main Account', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(TeacherSeeder::class);
    $this->seed(StaffSeeder::class);

    $account = SchoolAccount::where('name', 'Main Account')->sole();

    expect(TeacherProfile::count())->toBeGreaterThan(0)
        ->and(StaffProfile::count())->toBeGreaterThan(0)
        ->and(TeacherProfile::where('default_school_account_id', $account->id)->count())->toBe(TeacherProfile::count())
        ->and(StaffProfile::where('default_school_account_id', $account->id)->count())->toBe(StaffProfile::count());
});
