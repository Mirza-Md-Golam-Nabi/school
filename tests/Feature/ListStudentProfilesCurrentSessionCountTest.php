<?php

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentProfiles\Pages\ListStudentProfiles;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeListStudentProfilesCountTestStudent(Classes $class, int $rollNo, int $sessionYear, StudentStatus $status = StudentStatus::Active): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student])->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => $sessionYear,
        'gender' => Gender::Male,
        'status' => $status,
    ]);
}

it('counts only active students from the current session year per class', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 7', 'order' => 7, 'is_active' => true]);
    $currentYear = now()->year;

    makeListStudentProfilesCountTestStudent($class, 1, $currentYear);
    makeListStudentProfilesCountTestStudent($class, 2, $currentYear);
    makeListStudentProfilesCountTestStudent($class, 3, $currentYear - 1); // different session
    makeListStudentProfilesCountTestStudent($class, 4, $currentYear + 1); // different session
    makeListStudentProfilesCountTestStudent($class, 5, $currentYear, StudentStatus::Graduated); // not active

    $classes = (new ListStudentProfiles)->getViewData()['classes'];

    expect($classes->firstWhere('id', $class->id)->student_profiles_count)->toBe(2);
});
