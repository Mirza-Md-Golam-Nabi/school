<?php

use App\Enums\EmploymentStatus;
use App\Filament\Resources\TeacherProfiles\Pages\ListTeacherProfiles;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createSortTestTeacher(string $name): TeacherProfile
{
    return TeacherProfile::factory()->create([
        'status' => EmploymentStatus::Active,
        'user_id' => User::factory()->create(['name' => $name]),
    ]);
}

it('sorts teacher profiles by name by default', function () {
    $admin = grantSuperAdmin(User::factory()->create());
    $this->actingAs($admin);

    createSortTestTeacher('Zaman Khan');
    createSortTestTeacher('Anisa Rahman');
    createSortTestTeacher('Mizan Chowdhury');

    Livewire::test(ListTeacherProfiles::class)
        ->assertOk()
        ->assertSeeInOrder(['Anisa Rahman', 'Mizan Chowdhury', 'Zaman Khan']);
});

it('re-sorts by name descending when the Name column header is clicked', function () {
    $admin = grantSuperAdmin(User::factory()->create());
    $this->actingAs($admin);

    createSortTestTeacher('Zaman Khan');
    createSortTestTeacher('Anisa Rahman');

    Livewire::test(ListTeacherProfiles::class)
        ->sortTable('user.name', 'desc')
        ->assertOk()
        ->assertSeeInOrder(['Zaman Khan', 'Anisa Rahman']);
});
