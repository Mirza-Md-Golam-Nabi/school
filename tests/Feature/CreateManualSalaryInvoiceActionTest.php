<?php

use App\Actions\CreateManualSalaryInvoiceAction;
use App\Enums\EmploymentStatus;
use App\Enums\InvoiceStatus;
use App\Models\TeacherProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a flat manual invoice with no component breakdown', function () {
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active, 'joining_date' => '2026-07-10']);

    $invoice = app(CreateManualSalaryInvoiceAction::class)->handle(
        TeacherProfile::class,
        $teacher->id,
        7,
        2026,
        8000,
    );

    expect((float) $invoice->gross_amount)->toBe(8000.0)
        ->and((float) $invoice->deduction_amount)->toBe(0.0)
        ->and((float) $invoice->net_amount)->toBe(8000.0);

    expect($invoice)
        ->is_manual->toBeTrue()
        ->status->toBe(InvoiceStatus::Unpaid)
        ->salary_structure_id->toBeNull();

    expect($invoice->invoice_no)->toStartWith('SAL-2026-07-');
    expect($invoice->components)->toHaveCount(0);
});

it('refuses to create a manual invoice when one already exists for the same month', function () {
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);

    app(CreateManualSalaryInvoiceAction::class)->handle(TeacherProfile::class, $teacher->id, 7, 2026, 8000);

    expect(fn () => app(CreateManualSalaryInvoiceAction::class)->handle(TeacherProfile::class, $teacher->id, 7, 2026, 9000))
        ->toThrow(RuntimeException::class);
});
