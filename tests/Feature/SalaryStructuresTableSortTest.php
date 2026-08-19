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

function createSortTestStructure(TeacherProfile|StaffProfile $profile, ?string $effectiveTo = null): SalaryStructure
{
    return SalaryStructure::create([
        'profileable_type' => $profile::class,
        'profileable_id' => $profile->id,
        'use_components' => false,
        'flat_amount' => 10000,
        'effective_from' => '2026-01-01',
        'effective_to' => $effectiveTo,
    ]);
}

it('defaults to open-ended structures first, most recently created first within each group', function () {
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

    // Closed structures created first (lower ids), open-ended ones created after (higher ids).
    createSortTestStructure($zaman, '2025-12-31'); // closed, id 1
    createSortTestStructure($anisa, '2025-12-31'); // closed, id 2
    createSortTestStructure($mizan); // open-ended, id 3

    Livewire::test(ListSalaryStructures::class)
        ->assertOk()
        // Open-ended (Mizan) first, then closed structures newest-id-first (Anisa before Zaman).
        ->assertSeeInOrder(['Mizan Chowdhury', 'Anisa Rahman', 'Zaman Khan']);
});

it('orders closed structures by id descending when several are closed', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $firstTeacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active, 'user_id' => User::factory()->create(['name' => 'Rahim Uddin'])]);
    $secondTeacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active, 'user_id' => User::factory()->create(['name' => 'Karim Ahmed'])]);

    $first = createSortTestStructure($firstTeacher, '2025-06-30');
    $second = createSortTestStructure($secondTeacher, '2025-12-31');

    expect($second->id)->toBeGreaterThan($first->id);

    Livewire::test(ListSalaryStructures::class)
        ->assertOk()
        ->assertSeeInOrder(['Karim Ahmed', 'Rahim Uddin']);
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
