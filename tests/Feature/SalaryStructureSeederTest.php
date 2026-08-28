<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\UserType;
use App\Models\SalaryStructure;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Database\Seeders\SalaryStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('gives every active teacher and staff member a flat-amount salary structure between 10,000 and 15,000', function () {
    $teacherUser = User::factory()->create(['user_type' => UserType::Teacher]);
    $teacher = TeacherProfile::create([
        'user_id' => $teacherUser->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    $staffUser = User::factory()->create(['user_type' => UserType::Staff]);
    $staff = StaffProfile::create([
        'user_id' => $staffUser->id,
        'gender' => Gender::Female,
        'status' => EmploymentStatus::Active,
    ]);

    // An inactive teacher must not get a structure.
    $inactiveUser = User::factory()->create(['user_type' => UserType::Teacher]);
    TeacherProfile::create([
        'user_id' => $inactiveUser->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Resigned,
    ]);

    (new SalaryStructureSeeder)->run();

    expect(SalaryStructure::count())->toBe(2);

    $teacherStructure = SalaryStructure::where('profileable_type', TeacherProfile::class)
        ->where('profileable_id', $teacher->id)
        ->sole();

    $staffStructure = SalaryStructure::where('profileable_type', StaffProfile::class)
        ->where('profileable_id', $staff->id)
        ->sole();

    foreach ([$teacherStructure, $staffStructure] as $structure) {
        expect($structure->use_components)->toBeFalse()
            ->and((float) $structure->flat_amount)->toBeGreaterThanOrEqual(10000)
            ->and((float) $structure->flat_amount)->toBeLessThanOrEqual(15000)
            ->and($structure->effective_to)->toBeNull();
    }
});

it('does not create a second open structure for someone who already has one', function () {
    $user = User::factory()->create(['user_type' => UserType::Teacher]);
    $teacher = TeacherProfile::create([
        'user_id' => $user->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    (new SalaryStructureSeeder)->run();
    (new SalaryStructureSeeder)->run();

    expect(SalaryStructure::where('profileable_type', TeacherProfile::class)->where('profileable_id', $teacher->id)->count())
        ->toBe(1);
});
