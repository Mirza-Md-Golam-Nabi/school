<?php

use App\Actions\CollectPromotionFeesAction;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PromotionStatus;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Pages\PromoteStudentsForClass;
use App\Models\Classes;
use App\Models\FeeDiscount;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\StudentFeeDiscount;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makePromotionFeeTestStudent(Classes $class, int $sessionYear): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student])->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => $sessionYear,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function makePromotionFeeTestStructure(Classes $class, int $sessionYear, string $name, bool $isMonthly, float $amount): FeeStructure
{
    return FeeStructure::create([
        'class_id' => $class->id,
        'fee_type_id' => FeeType::create(['name' => $name, 'is_monthly' => $isMonthly, 'is_active' => true])->id,
        'amount' => $amount,
        'due_day' => 10,
        'session_year' => $sessionYear,
        'is_active' => true,
    ]);
}

/**
 * @return array<string, mixed>
 */
function promotionFeeTestPayment(float $amount, string $receiptNo = 'RCP-PROMO-1'): array
{
    return [
        'amount_paid' => $amount,
        'receipt_no' => $receiptNo,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
    ];
}

it('creates one invoice per one-time fee and per selected month, then records a partial payment', function () {
    $class = Classes::create(['name' => 'Fee Class 6', 'order' => 6]);
    $year = now()->year + 1;
    $student = makePromotionFeeTestStudent($class, $year - 1);

    $session = makePromotionFeeTestStructure($class, $year, 'Session Charge', false, 1000);
    $tuition = makePromotionFeeTestStructure($class, $year, 'Tuition Fee', true, 500);

    $payment = app(CollectPromotionFeesAction::class)->handle(
        $student,
        $class->id,
        $year,
        [$session->id, $tuition->id],
        [1, 2],
        promotionFeeTestPayment(1200),
    );

    $invoices = StudentFeeInvoice::where('student_id', $student->id)->where('year', $year)->get();

    expect($invoices)->toHaveCount(3)
        ->and($invoices->where('fee_type_id', $tuition->fee_type_id)->pluck('month')->sort()->values()->all())->toBe([1, 2])
        ->and($invoices->firstWhere('fee_type_id', $session->fee_type_id)->month)->toBeNull()
        ->and((float) FeePayment::where('payment_batch_id', $payment->payment_batch_id)->sum('amount_paid'))->toBe(1200.0)
        // Monthly invoices are settled before one-time ones, so the shortfall lands on the session charge.
        ->and($invoices->where('fee_type_id', $tuition->fee_type_id)->pluck('status')->unique()->all())->toBe([InvoiceStatus::Paid])
        ->and($invoices->firstWhere('fee_type_id', $session->fee_type_id)->status)->toBe(InvoiceStatus::Partial);
});

it('only creates unpaid invoices when no amount is received', function () {
    $class = Classes::create(['name' => 'Fee Class 6', 'order' => 6]);
    $year = now()->year + 1;
    $student = makePromotionFeeTestStudent($class, $year - 1);
    $session = makePromotionFeeTestStructure($class, $year, 'Session Charge', false, 1000);

    $payment = app(CollectPromotionFeesAction::class)->handle($student, $class->id, $year, [$session->id], [], ['amount_paid' => null]);

    expect($payment)->toBeNull()
        ->and(FeePayment::count())->toBe(0)
        ->and(StudentFeeInvoice::where('student_id', $student->id)->sole()->status)->toBe(InvoiceStatus::Unpaid);
});

it('reuses an existing invoice instead of creating a duplicate and ignores fee structures of another class', function () {
    $class = Classes::create(['name' => 'Fee Class 6', 'order' => 6]);
    $otherClass = Classes::create(['name' => 'Fee Class 7', 'order' => 7]);
    $year = now()->year + 1;
    $student = makePromotionFeeTestStudent($class, $year - 1);

    $session = makePromotionFeeTestStructure($class, $year, 'Session Charge', false, 1000);
    $otherClassFee = makePromotionFeeTestStructure($otherClass, $year, 'Lab Fee', false, 700);

    $existing = StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $session->fee_type_id,
        'month' => null,
        'year' => $year,
        'original_amount' => 1000,
        'net_amount' => 1000,
        'status' => InvoiceStatus::Unpaid,
    ]);

    $action = app(CollectPromotionFeesAction::class);

    expect($action->payableTotal($student, $class->id, $year, [$session->id, $otherClassFee->id], []))->toBe(1000.0);

    $payment = $action->handle($student, $class->id, $year, [$session->id, $otherClassFee->id], [], promotionFeeTestPayment(5000));

    expect(StudentFeeInvoice::where('student_id', $student->id)->count())->toBe(1)
        ->and($payment->invoice_id)->toBe($existing->id)
        ->and((float) $payment->amount_paid)->toBe(1000.0)
        ->and($existing->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it("applies the student's new-session discount to the invoice", function () {
    $class = Classes::create(['name' => 'Fee Class 6', 'order' => 6]);
    $year = now()->year + 1;
    $student = makePromotionFeeTestStudent($class, $year - 1);
    $session = makePromotionFeeTestStructure($class, $year, 'Session Charge', false, 1000);

    StudentFeeDiscount::create([
        'student_id' => $student->id,
        'fee_type_id' => $session->fee_type_id,
        'discount_id' => FeeDiscount::create(['name' => 'Half', 'discount_type' => 'percent', 'discount_value' => 50])->id,
        'session_year' => $year,
    ]);

    $action = app(CollectPromotionFeesAction::class);

    expect($action->payableTotal($student, $class->id, $year, [$session->id], []))->toBe(500.0);

    $action->handle($student, $class->id, $year, [$session->id], [], ['amount_paid' => 0]);

    $invoice = StudentFeeInvoice::where('student_id', $student->id)->sole();

    expect((float) $invoice->discount_amount)->toBe(500.0)
        ->and((float) $invoice->net_amount)->toBe(500.0);
});

it("offers the target class's next-session fees in the promote modal", function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $fromClass = Classes::create(['name' => 'Fee Promote Class 5', 'order' => 5]);
    $toClass = Classes::create(['name' => 'Fee Promote Class 6', 'order' => 6]);
    $year = now()->year;
    $student = makePromotionFeeTestStudent($fromClass, $year);
    makePromotionFeeTestStructure($toClass, $year + 1, 'Session Charge', false, 1000);

    Livewire::test(PromoteStudentsForClass::class, ['classId' => $fromClass->id, 'year' => $year])
        ->mountAction(TestAction::make('promote')->table($student))
        ->assertSchemaComponentVisible('fee_structure_ids')
        ->assertSchemaComponentHidden('fee_structure_warning')
        ->assertSchemaComponentHidden('amount_paid');
});

it('warns instead of offering fees when the target class has no fee structure for the new session', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $fromClass = Classes::create(['name' => 'Fee Promote Class 5', 'order' => 5]);
    $toClass = Classes::create(['name' => 'Fee Promote Class 6', 'order' => 6]);
    $year = now()->year;
    $student = makePromotionFeeTestStudent($fromClass, $year);
    // Current session's structure must not count — fees belong to the new session.
    makePromotionFeeTestStructure($toClass, $year, 'Session Charge', false, 1000);

    Livewire::test(PromoteStudentsForClass::class, ['classId' => $fromClass->id, 'year' => $year])
        ->mountAction(TestAction::make('promote')->table($student))
        ->assertSchemaComponentVisible('fee_structure_warning')
        ->assertSchemaComponentHidden('fee_structure_ids');
});

it('switches the target class to the current class for Repeated and back to the next class for Promoted', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $fromClass = Classes::create(['name' => 'Fee Promote Class 5', 'order' => 5]);
    $toClass = Classes::create(['name' => 'Fee Promote Class 6', 'order' => 6]);
    $year = now()->year;
    $student = makePromotionFeeTestStudent($fromClass, $year);

    Livewire::test(PromoteStudentsForClass::class, ['classId' => $fromClass->id, 'year' => $year])
        ->mountAction(TestAction::make('promote')->table($student))
        ->assertSchemaStateSet(['class_id' => $toClass->id])
        ->fillForm(['status' => PromotionStatus::Repeated->value])
        ->assertSchemaStateSet(['class_id' => $fromClass->id])
        ->fillForm(['status' => PromotionStatus::Promoted->value])
        ->assertSchemaStateSet(['class_id' => $toClass->id]);
});

it('promotes the student and collects the selected fees in one step', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $fromClass = Classes::create(['name' => 'Fee Promote Class 5', 'order' => 5]);
    $toClass = Classes::create(['name' => 'Fee Promote Class 6', 'order' => 6]);
    $year = now()->year;
    $student = makePromotionFeeTestStudent($fromClass, $year);

    $session = makePromotionFeeTestStructure($toClass, $year + 1, 'Session Charge', false, 1000);
    $tuition = makePromotionFeeTestStructure($toClass, $year + 1, 'Tuition Fee', true, 500);

    Livewire::test(PromoteStudentsForClass::class, ['classId' => $fromClass->id, 'year' => $year])
        ->callAction(TestAction::make('promote')->table($student), [
            'status' => 'promoted',
            'class_id' => $toClass->id,
            'roll_no' => 3,
            'fee_structure_ids' => [$session->id, $tuition->id],
            'fee_months' => [1],
            'amount_paid' => 1200,
            'payment_method' => PaymentMethod::Cash->value,
            'receipt_no' => 'RCP-PROMO-MODAL',
            'payment_date' => now()->toDateString(),
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Fee payment recorded');

    $invoices = StudentFeeInvoice::where('student_id', $student->id)->where('year', $year + 1)->get();

    expect($student->fresh()->current_class_id)->toBe($toClass->id)
        ->and($invoices)->toHaveCount(2)
        ->and($invoices->firstWhere('fee_type_id', $tuition->fee_type_id)->status)->toBe(InvoiceStatus::Paid)
        ->and($invoices->firstWhere('fee_type_id', $session->fee_type_id)->status)->toBe(InvoiceStatus::Partial)
        ->and($invoices->firstWhere('fee_type_id', $tuition->fee_type_id)->month)->toBe(1)
        ->and((float) FeePayment::where('student_id', $student->id)->sum('amount_paid'))->toBe(1200.0);
});

it('collects the current class fees of the new session when the student repeats', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $fromClass = Classes::create(['name' => 'Fee Promote Class 5', 'order' => 5]);
    Classes::create(['name' => 'Fee Promote Class 6', 'order' => 6]);
    $year = now()->year;
    $student = makePromotionFeeTestStudent($fromClass, $year);
    $session = makePromotionFeeTestStructure($fromClass, $year + 1, 'Session Charge', false, 800);

    Livewire::test(PromoteStudentsForClass::class, ['classId' => $fromClass->id, 'year' => $year])
        ->callAction(TestAction::make('promote')->table($student), [
            'status' => 'repeated',
            'class_id' => $fromClass->id,
            'roll_no' => 9,
            'fee_structure_ids' => [$session->id],
            'amount_paid' => null,
        ])
        ->assertHasNoActionErrors();

    $invoice = StudentFeeInvoice::where('student_id', $student->id)->sole();

    expect($student->fresh()->current_class_id)->toBe($fromClass->id)
        ->and($invoice->year)->toBe($year + 1)
        ->and($invoice->status)->toBe(InvoiceStatus::Unpaid)
        ->and(FeePayment::count())->toBe(0);
});

it('promotes without touching fees when no fee is selected', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $fromClass = Classes::create(['name' => 'Fee Promote Class 5', 'order' => 5]);
    $toClass = Classes::create(['name' => 'Fee Promote Class 6', 'order' => 6]);
    $year = now()->year;
    $student = makePromotionFeeTestStudent($fromClass, $year);
    makePromotionFeeTestStructure($toClass, $year + 1, 'Session Charge', false, 1000);

    Livewire::test(PromoteStudentsForClass::class, ['classId' => $fromClass->id, 'year' => $year])
        ->callAction(TestAction::make('promote')->table($student), [
            'status' => 'promoted',
            'class_id' => $toClass->id,
            'roll_no' => 3,
        ])
        ->assertHasNoActionErrors();

    expect($student->fresh()->current_class_id)->toBe($toClass->id)
        ->and(StudentFeeInvoice::count())->toBe(0)
        ->and(FeePayment::count())->toBe(0);
});

it('rejects an amount larger than the total payable', function () {
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $fromClass = Classes::create(['name' => 'Fee Promote Class 5', 'order' => 5]);
    $toClass = Classes::create(['name' => 'Fee Promote Class 6', 'order' => 6]);
    $year = now()->year;
    $student = makePromotionFeeTestStudent($fromClass, $year);
    $session = makePromotionFeeTestStructure($toClass, $year + 1, 'Session Charge', false, 1000);

    Livewire::test(PromoteStudentsForClass::class, ['classId' => $fromClass->id, 'year' => $year])
        ->callAction(TestAction::make('promote')->table($student), [
            'status' => 'promoted',
            'class_id' => $toClass->id,
            'roll_no' => 3,
            'fee_structure_ids' => [$session->id],
            'amount_paid' => 1500,
            'payment_method' => PaymentMethod::Cash->value,
            'receipt_no' => 'RCP-PROMO-OVER',
            'payment_date' => now()->toDateString(),
        ])
        ->assertHasActionErrors(['amount_paid']);

    expect($student->fresh()->current_class_id)->toBe($fromClass->id)
        ->and(StudentFeeInvoice::count())->toBe(0);
});
