<?php

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeStatusTestStudent(StudentStatus $status): StudentProfile
{
    $class = Classes::firstOrCreate(['name' => 'Class 6'], ['order' => 6, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => random_int(1, 9999),
        'current_class_id' => $class->id,
        'session_year' => 2026,
        'gender' => Gender::Male,
        'status' => $status,
    ]);
}

it('treats an inactive student as a former student, not an active one', function () {
    $active = makeStatusTestStudent(StudentStatus::Active);
    $inactive = makeStatusTestStudent(StudentStatus::Inactive);

    expect(StudentProfile::active()->pluck('id')->all())->toBe([$active->id])
        ->and(StudentProfile::former()->pluck('id')->all())->toBe([$inactive->id])
        ->and($inactive->fresh()->status)->toBe(StudentStatus::Inactive)
        ->and(StudentStatus::Inactive->getLabel())->toBe('Inactive');
});
