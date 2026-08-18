<?php

use App\Actions\GenerateMonthlyFeeInvoicesAction;
use App\Actions\GenerateOneTimeFeeInvoicesAction;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function makeInvoiceGenerationTestStudent(int $classId): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => random_int(1, 100000),
        'current_class_id' => $classId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it('logs a single summary entry when one-time invoices are generated manually', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 6', 'order' => 6]);
    $feeType = FeeType::create(['name' => 'Admission Fee', 'is_monthly' => false, 'is_active' => true]);

    $structure = FeeStructure::create([
        'class_id' => $class->id,
        'fee_type_id' => $feeType->id,
        'amount' => 2000,
        'due_day' => 10,
        'session_year' => 2026,
        'is_active' => true,
    ]);

    makeInvoiceGenerationTestStudent($class->id);
    makeInvoiceGenerationTestStudent($class->id);

    app(GenerateOneTimeFeeInvoicesAction::class)->handle($structure);

    $activities = Activity::where('log_name', 'fee_invoice_generation')->where('event', 'generated')->get();

    expect($activities)->toHaveCount(1);

    $activity = $activities->first();

    expect($activity->causer_id)->toBe($admin->id)
        ->and($activity->subject_id)->toBe($structure->id)
        ->and($activity->description)->toBe('Generated 2 one-time "Admission Fee" invoice(s) for Class 6 (2026) — 0 skipped (already invoiced).');

    expect($activity->properties->get('attributes'))
        ->toMatchArray([
            'class_id' => $class->id,
            'class_id_label' => 'Class 6',
            'fee_type_id' => $feeType->id,
            'fee_type_id_label' => 'Admission Fee',
            'session_year' => 2026,
            'generated_count' => 2,
            'skipped_count' => 0,
        ]);
});

it('logs a single summary entry when monthly invoices are generated manually for all classes', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 7', 'order' => 7, 'is_active' => true]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);

    FeeStructure::create([
        'class_id' => $class->id,
        'fee_type_id' => $feeType->id,
        'amount' => 500,
        'due_day' => 10,
        'session_year' => 2026,
        'is_active' => true,
    ]);

    makeInvoiceGenerationTestStudent($class->id);

    app(GenerateMonthlyFeeInvoicesAction::class)->handle(8, 2026);

    $activities = Activity::where('log_name', 'fee_invoice_generation')->where('event', 'generated')->get();

    expect($activities)->toHaveCount(1);

    $activity = $activities->first();

    expect($activity->causer_id)->toBe($admin->id)
        ->and($activity->description)->toBe('Generated 1 monthly fee invoice(s) for All Classes (August 2026) — 0 skipped (already invoiced).');

    expect($activity->properties->get('attributes'))
        ->toMatchArray([
            'class_id' => null,
            'month' => 8,
            'year' => 2026,
            'generated_count' => 1,
            'skipped_count' => 0,
        ]);
});

it('shows the specific class in the description when monthly invoices are generated for one class', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 8', 'order' => 8, 'is_active' => true]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);

    FeeStructure::create([
        'class_id' => $class->id,
        'fee_type_id' => $feeType->id,
        'amount' => 500,
        'due_day' => 10,
        'session_year' => 2026,
        'is_active' => true,
    ]);

    makeInvoiceGenerationTestStudent($class->id);

    app(GenerateMonthlyFeeInvoicesAction::class)->handle(8, 2026, $class->id);

    $activity = Activity::where('log_name', 'fee_invoice_generation')->where('event', 'generated')->first();

    expect($activity)->not->toBeNull()
        ->description->toBe('Generated 1 monthly fee invoice(s) for Class 8 (August 2026) — 0 skipped (already invoiced).');
});
