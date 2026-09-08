<?php

use App\Enums\UserType;
use App\Filament\Resources\StudentProfiles\Pages\StudentsByClass;
use App\Models\Classes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows the group filter instead of the class filter when browsing a grouped class', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 9', 'order' => 9, 'is_active' => true, 'has_group' => true]);

    Livewire::test(StudentsByClass::class, ['classId' => $class->id])
        ->assertTableFilterHidden('current_class_id')
        ->assertTableFilterVisible('current_group_id');
});

it('hides both the class and group filters when browsing a non-grouped class', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 6', 'order' => 6, 'is_active' => true, 'has_group' => false]);

    Livewire::test(StudentsByClass::class, ['classId' => $class->id])
        ->assertTableFilterHidden('current_class_id')
        ->assertTableFilterHidden('current_group_id');
});

it('shows the class filter (not the group filter) when no specific class is selected', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    Livewire::test(StudentsByClass::class, ['classId' => 0])
        ->assertTableFilterVisible('current_class_id')
        ->assertTableFilterHidden('current_group_id');
});
