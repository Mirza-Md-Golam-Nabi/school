<?php

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentProfiles\Pages\StudentsByClass;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeCurrentSessionTestStudent(Classes $class, int $rollNo, int $sessionYear): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student])->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => $sessionYear,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it('shows only students from the current session year, with no cohort-split headings', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 9', 'order' => 9, 'is_active' => true]);
    $currentYear = now()->year;

    $currentSessionStudent = makeCurrentSessionTestStudent($class, 1, $currentYear);
    $lastYearStudent = makeCurrentSessionTestStudent($class, 2, $currentYear - 1);
    $nextYearStudent = makeCurrentSessionTestStudent($class, 3, $currentYear + 1);

    Livewire::test(StudentsByClass::class, ['classId' => $class->id])
        ->assertCanSeeTableRecords([$currentSessionStudent])
        ->assertCanNotSeeTableRecords([$lastYearStudent, $nextYearStudent])
        ->assertDontSee('Promote বাকি আছে Students')
        ->assertDontSee('নতুন উত্তীর্ণ হওয়া Students');
});
