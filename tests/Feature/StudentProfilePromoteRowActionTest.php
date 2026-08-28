<?php

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentProfiles\Pages\StudentsByClass;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('no longer shows a promote action on the Student Profile class list — that moved to the Promote menu', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Row Promote Removed Class', 'order' => 1]);

    $user = User::factory()->create(['user_type' => UserType::Student]);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    test()->actingAs($admin);

    Livewire::test(StudentsByClass::class, ['classId' => $class->id])
        ->assertActionDoesNotExist(TestAction::make('promote')->table($student));
});
