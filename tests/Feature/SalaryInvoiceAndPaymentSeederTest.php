<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\UserType;
use App\Models\SalaryInvoice;
use App\Models\SalaryStructure;
use App\Models\SchoolAccount;
use App\Models\TeacherProfile;
use App\Models\User;
use Database\Seeders\SalaryInvoiceSeeder;
use Database\Seeders\SalaryPaymentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // ProcessIndividualSalaryPaymentAction notifies admins after each payment,
    // which requires these roles to exist.
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
});

it('generates 5 months of invoices and pays exactly 70% of each from Main Account, leaving 30% due', function () {
    $account = SchoolAccount::create(['name' => 'Main Account', 'current_balance' => 0]);

    $user = User::factory()->create(['user_type' => UserType::Teacher]);
    $teacher = TeacherProfile::create([
        'user_id' => $user->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
        'default_school_account_id' => $account->id,
    ]);

    SalaryStructure::create([
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacher->id,
        'use_components' => false,
        'flat_amount' => 12000,
        'effective_from' => now()->subYear()->toDateString(),
        'created_by' => $user->id,
    ]);

    (new SalaryInvoiceSeeder)->run();

    $invoices = SalaryInvoice::where('profileable_type', TeacherProfile::class)
        ->where('profileable_id', $teacher->id)
        ->get();

    expect($invoices)->toHaveCount(5)
        ->and($invoices->every(fn (SalaryInvoice $invoice) => (float) $invoice->net_amount === 12000.0))->toBeTrue();

    (new SalaryPaymentSeeder)->run();

    $invoices = $invoices->fresh();

    foreach ($invoices as $invoice) {
        expect($invoice->status)->toBe(InvoiceStatus::Partial)
            ->and((float) $invoice->total_paid)->toBe(8400.0) // 70% of 12000
            ->and((float) $invoice->due_amount)->toBe(3600.0); // remaining 30%
    }

    // ৳8,400 × 5 months paid out of Main Account, starting from a zero balance.
    expect((float) $account->fresh()->current_balance)->toBe(-42000.0);
});

it('does not re-pay an invoice that already has a partial payment when the seeder runs again', function () {
    SchoolAccount::create(['name' => 'Main Account', 'current_balance' => 0]);

    $user = User::factory()->create(['user_type' => UserType::Teacher]);
    $teacher = TeacherProfile::create([
        'user_id' => $user->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    SalaryStructure::create([
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacher->id,
        'use_components' => false,
        'flat_amount' => 10000,
        'effective_from' => now()->subYear()->toDateString(),
        'created_by' => $user->id,
    ]);

    (new SalaryInvoiceSeeder)->run();
    (new SalaryPaymentSeeder)->run();
    (new SalaryPaymentSeeder)->run();

    $invoice = SalaryInvoice::where('profileable_type', TeacherProfile::class)
        ->where('profileable_id', $teacher->id)
        ->first();

    expect((float) $invoice->total_paid)->toBe(7000.0); // still just 70% of 10000, not doubled
});
