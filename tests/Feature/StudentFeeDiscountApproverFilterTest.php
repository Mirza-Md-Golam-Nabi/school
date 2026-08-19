<?php

use App\Enums\FeeDiscountType;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentFeeDiscounts\Pages\CreateStudentFeeDiscount;
use App\Models\FeeDiscount;
use App\Models\FeeType;
use App\Models\StudentProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeApproverFilterFormData(User $admin, ?int $approvedBy): array
{
    $student = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $feeType = FeeType::create(['name' => 'Tuition Fee '.str()->random(4), 'is_monthly' => true, 'is_active' => true]);
    $discount = FeeDiscount::create(['name' => 'Discount '.str()->random(4), 'discount_type' => FeeDiscountType::Percent, 'discount_value' => 10]);

    return [
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'discount_id' => $discount->id,
        'session_year' => now()->year,
        'approved_by' => $approvedBy,
    ];
}

it('rejects a student as the approver, since the dropdown is scoped to staff-side users', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $studentUser = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    Livewire::test(CreateStudentFeeDiscount::class)
        ->fillForm(makeApproverFilterFormData($admin, $studentUser->id))
        ->call('create')
        ->assertHasFormErrors(['approved_by']);
});

it('rejects an inactive teacher as the approver', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $inactiveTeacher = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => false]);

    Livewire::test(CreateStudentFeeDiscount::class)
        ->fillForm(makeApproverFilterFormData($admin, $inactiveTeacher->id))
        ->call('create')
        ->assertHasFormErrors(['approved_by']);
});

it('accepts an active teacher as the approver', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $activeTeacher = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);

    Livewire::test(CreateStudentFeeDiscount::class)
        ->fillForm(makeApproverFilterFormData($admin, $activeTeacher->id))
        ->call('create')
        ->assertHasNoFormErrors();
});
