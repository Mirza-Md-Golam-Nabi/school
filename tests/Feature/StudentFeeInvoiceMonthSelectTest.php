<?php

use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentFeeInvoices\Pages\CreateStudentFeeInvoice;
use App\Models\FeeType;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

function makeMonthSelectTestStudent(): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => random_int(1, 100000),
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it('shows month names as dropdown options instead of a free-text number field', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    Livewire::test(CreateStudentFeeInvoice::class)
        ->assertSee('January')
        ->assertSee('December');
});

it('creates an invoice with a month selected via the dropdown', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $student = makeMonthSelectTestStudent();
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);

    Livewire::test(CreateStudentFeeInvoice::class)
        ->fillForm([
            'student_id' => $student->id,
            'fee_type_id' => $feeType->id,
            'month' => 8,
            'year' => 2026,
            'original_amount' => 500,
            'net_amount' => 500,
            'status' => InvoiceStatus::Unpaid->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(StudentFeeInvoice::class, [
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => 8,
        'year' => 2026,
    ]);
});

it('creates a one-time invoice when the month dropdown is left empty', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $student = makeMonthSelectTestStudent();
    $feeType = FeeType::create(['name' => 'Admission Fee', 'is_monthly' => false, 'is_active' => true]);

    Livewire::test(CreateStudentFeeInvoice::class)
        ->fillForm([
            'student_id' => $student->id,
            'fee_type_id' => $feeType->id,
            'month' => null,
            'year' => 2026,
            'original_amount' => 1000,
            'net_amount' => 1000,
            'status' => InvoiceStatus::Unpaid->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(StudentFeeInvoice::class, [
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => null,
        'year' => 2026,
    ]);
});
