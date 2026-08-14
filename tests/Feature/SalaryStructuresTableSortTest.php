<?php

use App\Enums\EmploymentStatus;
use App\Enums\UserType;
use App\Filament\Resources\SalaryStructures\Pages\ListSalaryStructures;
use App\Models\SalaryStructure;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createSortTestStructure(TeacherProfile|StaffProfile $profile): SalaryStructure
{
    return SalaryStructure::create([
        'profileable_type' => $profile::class,
        'profileable_id' => $profile->id,
        'use_components' => false,
        'flat_amount' => 10000,
        'effective_from' => '2026-01-01',
    ]);
}

it('renders the list page and sorts by name across teachers and staff without error', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $zaman = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active, 'user_id' => User::factory()->create(['name' => 'Zaman Khan'])]);
    $anisa = StaffProfile::create([
        'user_id' => User::factory()->create(['name' => 'Anisa Rahman'])->id,
        'gender' => 'female',
        'nationality' => 'Bangladeshi',
        'status' => EmploymentStatus::Active,
    ]);
    $mizan = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active, 'user_id' => User::factory()->create(['name' => 'Mizan Chowdhury'])]);

    createSortTestStructure($zaman);
    createSortTestStructure($anisa);
    createSortTestStructure($mizan);

    Livewire::test(ListSalaryStructures::class)
        ->assertOk()
        ->assertSeeInOrder(['Anisa Rahman', 'Mizan Chowdhury', 'Zaman Khan']);
});

it('re-sorts by name descending when the Name column header is clicked', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $zaman = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active, 'user_id' => User::factory()->create(['name' => 'Zaman Khan'])]);
    $anisa = StaffProfile::create([
        'user_id' => User::factory()->create(['name' => 'Anisa Rahman'])->id,
        'gender' => 'female',
        'nationality' => 'Bangladeshi',
        'status' => EmploymentStatus::Active,
    ]);

    createSortTestStructure($zaman);
    createSortTestStructure($anisa);

    Livewire::test(ListSalaryStructures::class)
        ->sortTable('profileable.user.name', 'desc')
        ->assertOk()
        ->assertSeeInOrder(['Zaman Khan', 'Anisa Rahman']);
});
