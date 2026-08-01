<?php

use App\Actions\GenerateMonthlySalaryInvoicesAction;
use App\Enums\EmploymentStatus;
use App\Enums\SalaryComponentType;
use App\Models\SalaryComponent;
use App\Models\SalaryInvoice;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureComponent;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createGenerateTestStructure(TeacherProfile|StaffProfile $profile, array $overrides = []): SalaryStructure
{
    return SalaryStructure::create(array_merge([
        'profileable_type' => $profile::class,
        'profileable_id' => $profile->id,
        'use_components' => true,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
    ], $overrides));
}

it('generates a component-based invoice with a snapshot of the current structure components', function () {
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active, 'joining_date' => '2025-01-01']);
    $structure = createGenerateTestStructure($teacher);

    $basic = SalaryComponent::create(['name' => 'Basic', 'type' => SalaryComponentType::Allowance, 'is_active' => true]);
    $houseRent = SalaryComponent::create(['name' => 'House Rent', 'type' => SalaryComponentType::Allowance, 'is_active' => true]);
    $providentFund = SalaryComponent::create(['name' => 'Provident Fund', 'type' => SalaryComponentType::Deduction, 'is_active' => true]);

    SalaryStructureComponent::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $basic->id, 'amount' => 15000]);
    SalaryStructureComponent::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $houseRent->id, 'amount' => 5000]);
    SalaryStructureComponent::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $providentFund->id, 'amount' => 1000]);

    $result = app(GenerateMonthlySalaryInvoicesAction::class)->handle(7, 2026);

    expect($result['generated'])->toBe(1);

    $invoice = SalaryInvoice::where('profileable_id', $teacher->id)->where('profileable_type', TeacherProfile::class)->first();

    expect($invoice)->not->toBeNull();
    expect((float) $invoice->gross_amount)->toBe(20000.0)
        ->and((float) $invoice->deduction_amount)->toBe(1000.0)
        ->and((float) $invoice->net_amount)->toBe(19000.0);
    expect($invoice->is_manual)->toBeFalse();

    expect($invoice->invoice_no)->toStartWith('SAL-2026-07-');
    expect($invoice->components)->toHaveCount(3);
});

it('generates a flat-amount invoice when the structure does not use components', function () {
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active, 'joining_date' => '2025-01-01']);
    createGenerateTestStructure($teacher, ['use_components' => false, 'flat_amount' => 12000]);

    app(GenerateMonthlySalaryInvoicesAction::class)->handle(7, 2026);

    $invoice = SalaryInvoice::where('profileable_id', $teacher->id)->first();

    expect((float) $invoice->gross_amount)->toBe(12000.0)
        ->and((float) $invoice->net_amount)->toBe(12000.0);
    expect($invoice->components)->toHaveCount(0);
});

it('skips profiles that already have an invoice for the target month', function () {
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active, 'joining_date' => '2025-01-01']);
    createGenerateTestStructure($teacher, ['use_components' => false, 'flat_amount' => 12000]);

    $action = app(GenerateMonthlySalaryInvoicesAction::class);
    $first = $action->handle(7, 2026);
    $second = $action->handle(7, 2026);

    expect($first['generated'])->toBe(1)
        ->and($second['generated'])->toBe(0)
        ->and($second['skipped'])->toBe(1);
});

it('skips inactive teachers and staff', function () {
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Resigned, 'joining_date' => '2025-01-01']);
    createGenerateTestStructure($teacher, ['use_components' => false, 'flat_amount' => 12000]);

    $result = app(GenerateMonthlySalaryInvoicesAction::class)->handle(7, 2026);

    expect($result['generated'])->toBe(0);
    expect(SalaryInvoice::where('profileable_id', $teacher->id)->exists())->toBeFalse();
});

it('skips profiles that joined after the target month', function () {
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active, 'joining_date' => '2026-08-01']);
    createGenerateTestStructure($teacher, ['use_components' => false, 'flat_amount' => 12000, 'effective_from' => '2026-08-01']);

    $result = app(GenerateMonthlySalaryInvoicesAction::class)->handle(7, 2026);

    expect($result['generated'])->toBe(0);
    expect(SalaryInvoice::where('profileable_id', $teacher->id)->exists())->toBeFalse();
});

it('skips active profiles without an effective salary structure', function () {
    TeacherProfile::factory()->create(['status' => EmploymentStatus::Active, 'joining_date' => '2025-01-01']);

    $result = app(GenerateMonthlySalaryInvoicesAction::class)->handle(7, 2026);

    expect($result['generated'])->toBe(0)
        ->and($result['no_structure'])->toBe(1);
});

it('generates invoices for both teachers and staff', function () {
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active, 'joining_date' => '2025-01-01']);
    $staff = StaffProfile::factory()->create(['status' => EmploymentStatus::Active, 'joining_date' => '2025-01-01']);

    createGenerateTestStructure($teacher, ['use_components' => false, 'flat_amount' => 12000]);
    createGenerateTestStructure($staff, ['use_components' => false, 'flat_amount' => 8000]);

    $result = app(GenerateMonthlySalaryInvoicesAction::class)->handle(7, 2026);

    expect($result['generated'])->toBe(2);
    expect(SalaryInvoice::where('profileable_type', StaffProfile::class)->where('profileable_id', $staff->id)->exists())->toBeTrue();
});
