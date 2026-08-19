<?php

use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentFeeInvoices\Pages\CreateStudentFeeInvoice;
use App\Models\FeeType;
use App\Models\StudentProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeWaiverByFilterFormData(FeeType $feeType, StudentProfile $student, ?int $waiverBy): array
{
    return [
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'year' => now()->year,
        'original_amount' => 500,
        'net_amount' => 500,
        'status' => InvoiceStatus::Unpaid->value,
        'waiver_by' => $waiverBy,
    ];
}

function makeWaiverByFilterTestStudent(): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => random_int(1, 100000),
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it('rejects a student as the waiver approver, since the dropdown is scoped to staff-side users', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $student = makeWaiverByFilterTestStudent();
    $otherStudentUser = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    Livewire::test(CreateStudentFeeInvoice::class)
        ->fillForm(makeWaiverByFilterFormData($feeType, $student, $otherStudentUser->id))
        ->call('create')
        ->assertHasFormErrors(['waiver_by']);
});

it('rejects an inactive teacher as the waiver approver', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $student = makeWaiverByFilterTestStudent();
    $inactiveTeacher = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => false]);

    Livewire::test(CreateStudentFeeInvoice::class)
        ->fillForm(makeWaiverByFilterFormData($feeType, $student, $inactiveTeacher->id))
        ->call('create')
        ->assertHasFormErrors(['waiver_by']);
});

it('accepts an active teacher as the waiver approver', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $student = makeWaiverByFilterTestStudent();
    $activeTeacher = User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true]);

    Livewire::test(CreateStudentFeeInvoice::class)
        ->fillForm(makeWaiverByFilterFormData($feeType, $student, $activeTeacher->id))
        ->call('create')
        ->assertHasNoFormErrors();
});

it('lists admins and super admins before teachers and staff, alphabetical within each group', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true, 'name' => 'Zahid Admin']));
    test()->actingAs($admin);

    User::factory()->create(['name' => 'Zaman Sir', 'user_type' => UserType::Teacher, 'is_active' => true]);
    User::factory()->create(['name' => 'Anika Madam', 'user_type' => UserType::Staff, 'is_active' => true]);
    User::factory()->create(['name' => 'Karim Sir', 'user_type' => UserType::Teacher, 'is_active' => true]);
    User::factory()->create(['name' => 'Babul Super', 'user_type' => UserType::SuperAdmin, 'is_active' => true]);

    // Admin/Super Admin group ("Babul Super", "Zahid Admin") sorted alphabetically,
    // followed by the Teacher/Staff group ("Anika Madam", "Karim Sir", "Zaman Sir")
    // sorted alphabetically — even though "Anika" would sort before all admins alphabetically.
    Livewire::test(CreateStudentFeeInvoice::class)
        ->assertSeeInOrder(['Babul Super', 'Zahid Admin', 'Anika Madam', 'Karim Sir', 'Zaman Sir']);
});
