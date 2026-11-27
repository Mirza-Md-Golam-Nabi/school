<?php

use App\Actions\BuildStudentFeeSummaryAction;
use App\Actions\CalculateFeeDuesAction;
use App\Actions\CalculateFeeReconciliationAction;
use App\Actions\MarkFeeInvoicesAsDefaultAction;
use App\Actions\ProcessFeePaymentAction;
use App\Actions\RecoverDefaultedFeeInvoicesAction;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permissions\FeePermission;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentFeeInvoices\Pages\EditStudentFeeInvoice;
use App\Filament\Resources\StudentFeeInvoices\Pages\ManageClassStudentFeeInvoices;
use App\Filament\Resources\StudentProfiles\Pages\EditStudentProfile;
use App\Filament\Resources\StudentProfiles\StudentProfileResource;
use App\Models\AccountTransaction;
use App\Models\Classes;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\Permission;
use App\Models\SchoolAccount;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

function defaultTestStudent(?Classes $class = null, int $rollNo = 1): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class?->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function defaultTestInvoice(StudentProfile $student, int $month, float $netAmount = 1000, InvoiceStatus $status = InvoiceStatus::Unpaid): StudentFeeInvoice
{
    $account = SchoolAccount::firstOrCreate(['name' => 'Main Fund'], ['current_balance' => 0]);
    $feeType = FeeType::firstOrCreate(
        ['name' => 'Tuition Fee'],
        ['is_monthly' => true, 'is_active' => true, 'school_account_id' => $account->id],
    );

    return StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => $month,
        'year' => now()->year,
        'original_amount' => $netAmount,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => $netAmount,
        'status' => $status,
    ]);
}

function defaultTestPay(StudentFeeInvoice $invoice, float $amount, string $receiptNo = 'RCPT-1'): FeePayment
{
    return app(ProcessFeePaymentAction::class)->handle([
        'student_id' => $invoice->student_id,
        'invoice_ids' => [$invoice->id],
        'amount_paid' => $amount,
        'receipt_no' => $receiptNo,
        'payment_method' => PaymentMethod::Cash->value,
        'payment_date' => now()->toDateString(),
    ]);
}

function defaultTestAdmin(array $permissions = []): User
{
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']);
        $admin->givePermissionTo($permission->value);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $admin;
}

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('marks an unpaid invoice as defaulted without touching the school account', function () {
    $admin = defaultTestAdmin();
    $invoice = defaultTestInvoice(defaultTestStudent(), 9);

    $count = app(MarkFeeInvoicesAsDefaultAction::class)->handle([$invoice], $admin->id, 'স্কুল ছেড়ে গেছে');

    $invoice->refresh();

    expect($count)->toBe(1)
        ->and($invoice->status)->toBe(InvoiceStatus::Defaulted)
        ->and((float) $invoice->default_amount)->toBe(1000.0)
        ->and($invoice->defaulted_by)->toBe($admin->id)
        ->and($invoice->defaulted_at)->not->toBeNull()
        ->and($invoice->default_reason)->toBe('স্কুল ছেড়ে গেছে')
        ->and(AccountTransaction::count())->toBe(0);
});

it('defaults only the remaining part of a partially paid invoice and keeps the payment', function () {
    $admin = defaultTestAdmin();
    $invoice = defaultTestInvoice(defaultTestStudent(), 9);
    defaultTestPay($invoice, 400);

    app(MarkFeeInvoicesAsDefaultAction::class)->handle([$invoice->fresh()], $admin->id, 'আর দেবে না');

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Defaulted)
        ->and((float) $invoice->default_amount)->toBe(600.0)
        ->and($invoice->payments()->count())->toBe(1)
        ->and((float) AccountTransaction::sum('amount'))->toBe(400.0);
});

it('skips paid and waived invoices', function () {
    $admin = defaultTestAdmin();
    $student = defaultTestStudent();
    $paid = defaultTestInvoice($student, 1);
    defaultTestPay($paid, 1000);
    $waived = defaultTestInvoice($student, 2, status: InvoiceStatus::Waived);

    $count = app(MarkFeeInvoicesAsDefaultAction::class)->handle([$paid->fresh(), $waived], $admin->id, 'test');

    expect($count)->toBe(0)
        ->and($paid->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($waived->fresh()->status)->toBe(InvoiceStatus::Waived);
});

it('defaults all of a student\'s outstanding invoices at once', function () {
    $admin = defaultTestAdmin();
    $student = defaultTestStudent();

    foreach (range(1, 8) as $month) {
        defaultTestPay(defaultTestInvoice($student, $month), 1000, "RCPT-{$month}");
    }

    foreach (range(9, 12) as $month) {
        defaultTestInvoice($student, $month);
    }

    $count = app(MarkFeeInvoicesAsDefaultAction::class)->forStudent($student, $admin->id, '৪ মাস দেয়নি');

    expect($count)->toBe(4)
        ->and($student->defaultedDueAmount())->toBe(4000.0)
        ->and($student->feeInvoices()->where('status', InvoiceStatus::Paid)->count())->toBe(8)
        ->and((float) AccountTransaction::sum('amount'))->toBe(8000.0);
});

it('recovers a defaulted invoice so it can be paid again and the payment reaches the account', function () {
    $admin = defaultTestAdmin();
    $invoice = defaultTestInvoice(defaultTestStudent(), 9);
    defaultTestPay($invoice, 300);
    app(MarkFeeInvoicesAsDefaultAction::class)->handle([$invoice->fresh()], $admin->id, 'test');

    app(RecoverDefaultedFeeInvoicesAction::class)->handle([$invoice->fresh()]);

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Partial)
        ->and((float) $invoice->default_amount)->toBe(0.0)
        ->and($invoice->defaulted_by)->toBeNull()
        ->and($invoice->default_reason)->toBeNull();

    defaultTestPay($invoice, 700, 'RCPT-2');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and((float) AccountTransaction::sum('amount'))->toBe(1000.0);
});

it('refuses to take payment against a defaulted invoice', function () {
    $admin = defaultTestAdmin();
    $invoice = defaultTestInvoice(defaultTestStudent(), 9);
    app(MarkFeeInvoicesAsDefaultAction::class)->handle([$invoice], $admin->id, 'test');

    defaultTestPay($invoice->fresh(), 500);
})->throws(ValidationException::class);

it('keeps a defaulted invoice defaulted when an earlier payment is deleted', function () {
    $admin = defaultTestAdmin();
    $invoice = defaultTestInvoice(defaultTestStudent(), 9);
    $payment = defaultTestPay($invoice, 400);
    app(MarkFeeInvoicesAsDefaultAction::class)->handle([$invoice->fresh()], $admin->id, 'test');

    $payment->delete();

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Defaulted)
        ->and((float) $invoice->default_amount)->toBe(1000.0);
});

it('reconciles billed amount into collected, waived, defaulted and receivable', function () {
    $admin = defaultTestAdmin();
    $student = defaultTestStudent();

    defaultTestPay(defaultTestInvoice($student, 1), 1000, 'RCPT-1');
    $partial = defaultTestInvoice($student, 2);
    defaultTestPay($partial, 250, 'RCPT-2');
    defaultTestInvoice($student, 3, 500, InvoiceStatus::Waived);
    $toDefault = defaultTestInvoice($student, 4);
    defaultTestInvoice($student, 5);

    app(MarkFeeInvoicesAsDefaultAction::class)->handle([$toDefault], $admin->id, 'test');

    $summary = app(CalculateFeeReconciliationAction::class)->handle();

    expect($summary)->toBe([
        'billed' => 4500.0,
        'collected' => 1250.0,
        'waived' => 500.0,
        'defaulted' => 1000.0,
        'receivable' => 1750.0,
    ])->and($summary['collected'] + $summary['waived'] + $summary['defaulted'] + $summary['receivable'])
        ->toBe($summary['billed']);
});

it('hides the default action from an admin without the permission', function () {
    $invoice = defaultTestInvoice(defaultTestStudent(), 9);

    test()->actingAs(defaultTestAdmin([FeePermission::VIEW_STUDENT_FEE_INVOICES, FeePermission::EDIT_STUDENT_FEE_INVOICES]));

    Livewire::test(EditStudentFeeInvoice::class, ['record' => $invoice->id])
        ->assertActionHidden('markAsDefault');
});

it('lets an admin granted the permission default an invoice from the edit page', function () {
    $invoice = defaultTestInvoice(defaultTestStudent(), 9);

    test()->actingAs(defaultTestAdmin([
        FeePermission::VIEW_STUDENT_FEE_INVOICES,
        FeePermission::EDIT_STUDENT_FEE_INVOICES,
        FeePermission::MARK_FEE_DEFAULT,
    ]));

    Livewire::test(EditStudentFeeInvoice::class, ['record' => $invoice->id])
        ->assertActionVisible('markAsDefault')
        ->callAction('markAsDefault', ['default_reason' => 'যোগাযোগ নেই'])
        ->assertHasNoActionErrors();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Defaulted);
});

it('requires a reason when defaulting', function () {
    $invoice = defaultTestInvoice(defaultTestStudent(), 9);

    test()->actingAs(grantSuperAdmin(defaultTestAdmin()));

    Livewire::test(EditStudentFeeInvoice::class, ['record' => $invoice->id])
        ->callAction('markAsDefault', ['default_reason' => ''])
        ->assertHasActionErrors(['default_reason' => 'required']);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('lets super-admin default all of a student\'s dues from the class invoice page', function () {
    $class = Classes::create(['name' => 'Class Six', 'order' => 6, 'is_active' => true]);
    $student = defaultTestStudent($class);
    defaultTestInvoice($student, 9);
    defaultTestInvoice($student, 10);

    test()->actingAs(grantSuperAdmin(defaultTestAdmin()));

    Livewire::test(ManageClassStudentFeeInvoices::class, ['classId' => $class->id])
        ->callAction(TestAction::make('markStudentDuesAsDefault')->table($student), ['default_reason' => 'চলে গেছে']);

    expect($student->defaultedDueAmount())->toBe(2000.0);
});

it('shows old defaulted dues on the student profile and recovers them on re-admission', function () {
    $admin = defaultTestAdmin();
    $student = defaultTestStudent();
    $invoice = defaultTestInvoice($student, 9);
    app(MarkFeeInvoicesAsDefaultAction::class)->handle([$invoice], $admin->id, 'test');

    test()->actingAs(grantSuperAdmin(defaultTestAdmin()));

    Livewire::test(EditStudentProfile::class, ['record' => $student->id])
        ->assertActionVisible('recoverStudentDefaults')
        ->callAction('recoverStudentDefaults');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid)
        ->and($student->defaultedDueAmount())->toBe(0.0);
});

it('reconciles a single year with two queries', function () {
    $student = defaultTestStudent();

    $partial = defaultTestInvoice($student, 1);
    defaultTestPay($partial, 300, 'RCPT-1');
    $partial->update(['waiver_amount' => 100]);
    defaultTestInvoice($student, 2, 500, InvoiceStatus::Waived);
    defaultTestInvoice($student, 3, 700)->update(['year' => now()->year - 1]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $thisYear = app(CalculateFeeReconciliationAction::class)->handle(now()->year);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($thisYear)->toBe([
        'billed' => 1600.0,
        'collected' => 300.0,
        'waived' => 600.0,
        'defaulted' => 0.0,
        'receivable' => 700.0,
    ])->and($queryCount)->toBe(2)
        ->and(app(CalculateFeeReconciliationAction::class)->handle())->toBe([
            'billed' => 2300.0,
            'collected' => 300.0,
            'waived' => 600.0,
            'defaulted' => 0.0,
            'receivable' => 1400.0,
        ]);
});

it('calculates the dashboard dues with two queries', function () {
    $admin = defaultTestAdmin();
    $student = defaultTestStudent();

    defaultTestInvoice($student, 1);
    defaultTestPay(defaultTestInvoice($student, 2), 250, 'RCPT-1');
    defaultTestPay(defaultTestInvoice($student, 3), 1000, 'RCPT-2');
    defaultTestInvoice($student, 4, 500, InvoiceStatus::Waived);
    $toDefault = defaultTestInvoice($student, 5);
    app(MarkFeeInvoicesAsDefaultAction::class)->handle([$toDefault], $admin->id, 'test');

    DB::flushQueryLog();
    DB::enableQueryLog();
    $dues = app(CalculateFeeDuesAction::class)->handle();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($dues)->toBe([
        'payable_count' => 2,
        'total_due' => 1750.0,
        'total_defaulted' => 1000.0,
    ])->and($queryCount)->toBe(2);
});

it('summarises a student\'s fees on the profile view page', function () {
    $student = defaultTestStudent();

    defaultTestPay(defaultTestInvoice($student, 1), 1000, 'RCPT-FULL');
    defaultTestPay(defaultTestInvoice($student, 2, 800), 300, 'RCPT-PART');
    defaultTestInvoice($student, 3, 400);

    $summary = app(BuildStudentFeeSummaryAction::class)->handle($student);

    expect($summary['totalPaid'])->toBe(1300.0)
        ->and($summary['totalDue'])->toBe(900.0)
        ->and($summary['pendingInvoiceCount'])->toBe(2)
        ->and($summary['recentPayments']->pluck('receipt_no')->sort()->values()->all())->toBe(['RCPT-FULL', 'RCPT-PART']);

    $this->actingAs(grantSuperAdmin(defaultTestAdmin()))
        ->get(StudentProfileResource::getUrl('view', ['record' => $student]))
        ->assertOk()
        ->assertSee('৳1,300.00')
        ->assertSee('৳900.00')
        ->assertSee('RCPT-PART');
});
