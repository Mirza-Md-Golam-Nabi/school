<?php

use App\Actions\CreateManualSalaryInvoiceAction;
use App\Actions\GenerateMonthlySalaryInvoicesAction;
use App\Actions\ProcessIndividualSalaryPaymentAction;
use App\Enums\EmploymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\SalaryComponentType;
use App\Enums\UserType;
use App\Models\SalaryComponent;
use App\Models\SalaryInvoice;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureComponent;
use App\Models\SchoolAccount;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
});

function createSalaryLogTestInvoice(TeacherProfile $teacher, int $month, int $year, float $netAmount): SalaryInvoice
{
    return SalaryInvoice::create([
        'invoice_no' => 'SAL-'.$year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacher->id,
        'month' => $month,
        'year' => $year,
        'gross_amount' => $netAmount,
        'deduction_amount' => 0,
        'net_amount' => $netAmount,
        'status' => InvoiceStatus::Unpaid,
        'is_manual' => true,
    ]);
}

it('logs salary component creation and updates', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $component = SalaryComponent::create(['name' => 'Basic', 'type' => SalaryComponentType::Allowance, 'is_active' => true]);

    $createdActivity = Activity::where('log_name', 'salary_component')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->causer_id->toBe($admin->id)
        ->description->toBe('Created salary component "Basic" (Allowance).');

    $component->update(['name' => 'Basic Salary']);

    $updatedActivity = Activity::where('log_name', 'salary_component')->where('event', 'updated')->first();

    expect($updatedActivity)->not->toBeNull()
        ->description->toBe('Updated salary component "Basic Salary".');
});

it('logs salary structure and structure component changes with a resolved person label', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $teacherUser = User::factory()->create(['user_type' => UserType::Teacher, 'name' => 'Mr. Salary Teacher', 'is_active' => true]);
    $teacher = TeacherProfile::factory()->create(['user_id' => $teacherUser->id, 'status' => EmploymentStatus::Active]);

    $structure = SalaryStructure::create([
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacher->id,
        'use_components' => true,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
        'created_by' => $admin->id,
    ]);

    $structureActivity = Activity::where('log_name', 'salary_structure')->where('event', 'created')->first();

    expect($structureActivity)->not->toBeNull()
        ->description->toBe('Created salary structure for "Mr. Salary Teacher" (component-based amounts, from 2026-01-01).');

    $basic = SalaryComponent::create(['name' => 'Basic', 'type' => SalaryComponentType::Allowance, 'is_active' => true]);

    SalaryStructureComponent::create([
        'salary_structure_id' => $structure->id,
        'salary_component_id' => $basic->id,
        'amount' => 15000,
    ]);

    $componentActivity = Activity::where('log_name', 'salary_structure_component')->where('event', 'created')->first();

    expect($componentActivity)->not->toBeNull()
        ->description->toBe('Added "Basic" (৳15,000.00) to the salary structure for "Mr. Salary Teacher".');
});

it('logs a single summary entry when monthly salary invoices are generated', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active, 'joining_date' => '2025-01-01']);

    SalaryStructure::create([
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacher->id,
        'use_components' => false,
        'flat_amount' => 20000,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
    ]);

    app(GenerateMonthlySalaryInvoicesAction::class)->handle(7, 2026, $admin->id);

    $activities = Activity::where('log_name', 'salary_invoice_generation')->where('event', 'generated')->get();

    expect($activities)->toHaveCount(1);

    expect($activities->first())
        ->causer_id->toBe($admin->id)
        ->description->toBe('Generated 1 monthly salary invoice(s) for July 2026 — 0 skipped (already invoiced), 0 skipped (no salary structure).');
});

it('logs manual salary invoice creation with a resolved person label', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $teacherUser = User::factory()->create(['user_type' => UserType::Teacher, 'name' => 'Mr. Manual Invoice', 'is_active' => true]);
    $teacher = TeacherProfile::factory()->create(['user_id' => $teacherUser->id, 'status' => EmploymentStatus::Active]);

    $invoice = app(CreateManualSalaryInvoiceAction::class)->handle(TeacherProfile::class, $teacher->id, 7, 2026, 8000, $admin->id);

    $activity = Activity::where('log_name', 'salary_invoice_generation')->where('event', 'created')->first();

    expect($activity)->not->toBeNull()
        ->causer_id->toBe($admin->id)
        ->subject_id->toBe($invoice->id)
        ->description->toBe("Manually created salary invoice {$invoice->invoice_no} for \"Mr. Manual Invoice\" (July 2026) — ৳8,000.00.");
});

it('logs a salary payment with a resolved invoice label', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 100000]);
    $teacherUser = User::factory()->create(['user_type' => UserType::Teacher, 'name' => 'Mr. Payee', 'is_active' => true]);
    $teacher = TeacherProfile::factory()->create(['user_id' => $teacherUser->id, 'status' => EmploymentStatus::Active]);
    $invoice = createSalaryLogTestInvoice($teacher, 7, 2026, 20000);

    app(ProcessIndividualSalaryPaymentAction::class)->handle([
        'invoice_ids' => [$invoice->id],
        'amount_paid' => 20000,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
        'school_account_id' => $account->id,
        'paid_by' => $admin->id,
    ]);

    $activity = Activity::where('log_name', 'salary_payment')->where('event', 'created')->first();

    expect($activity)->not->toBeNull()
        ->causer_id->toBe($admin->id)
        ->description->toBe("Created salary payment of ৳20,000.00 for \"Mr. Payee\" - {$invoice->invoice_no} (July 2026).");

    expect(Activity::where('log_name', 'salary_bulk_payment')->count())->toBe(0);
});

it('logs a bulk payment wrapper plus individual payment entries when multiple invoices are paid in one submission', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 100000]);
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);
    $invoiceOne = createSalaryLogTestInvoice($teacher, 6, 2026, 10000);
    $invoiceTwo = createSalaryLogTestInvoice($teacher, 7, 2026, 10000);

    app(ProcessIndividualSalaryPaymentAction::class)->handle([
        'invoice_ids' => [$invoiceOne->id, $invoiceTwo->id],
        'amount_paid' => 20000,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
        'school_account_id' => $account->id,
        'paid_by' => $admin->id,
    ]);

    $paymentActivities = Activity::where('log_name', 'salary_payment')->where('event', 'created')->get();
    expect($paymentActivities)->toHaveCount(2);

    $bulkActivity = Activity::where('log_name', 'salary_bulk_payment')->where('event', 'created')->first();

    expect($bulkActivity)->not->toBeNull()
        ->causer_id->toBe($admin->id)
        ->description->toBe('Recorded a bulk salary payment of ৳20,000.00, paid by "'.$admin->name.'".');
});
