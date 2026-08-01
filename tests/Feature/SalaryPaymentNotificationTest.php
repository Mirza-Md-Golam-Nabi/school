<?php

use App\Actions\ProcessIndividualSalaryPaymentAction;
use App\Actions\ProcessPayrollBatchPaymentAction;
use App\Enums\EmploymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserType;
use App\Models\SalaryInvoice;
use App\Models\SchoolAccount;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Notifications\SalaryPaymentReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createSalaryNotificationTestAdmins(): array
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $admin = User::factory()->create(['user_type' => UserType::Admin]);
    $admin->assignRole('admin');

    $superAdmin = User::factory()->create(['user_type' => UserType::Admin]);
    $superAdmin->assignRole('super-admin');

    return [$admin, $superAdmin];
}

function createSalaryNotificationTestInvoice(TeacherProfile $teacher, float $netAmount): SalaryInvoice
{
    return SalaryInvoice::create([
        'invoice_no' => 'SAL-2026-07-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        'profileable_type' => TeacherProfile::class,
        'profileable_id' => $teacher->id,
        'month' => 7,
        'year' => 2026,
        'gross_amount' => $netAmount,
        'deduction_amount' => 0,
        'net_amount' => $netAmount,
        'status' => InvoiceStatus::Unpaid,
        'is_manual' => true,
    ]);
}

it('notifies admins, super-admins, and the paid teacher when a salary payment is made', function () {
    Notification::fake();

    [$admin, $superAdmin] = createSalaryNotificationTestAdmins();
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 100000]);
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);
    $invoice = createSalaryNotificationTestInvoice($teacher, 20000);

    app(ProcessIndividualSalaryPaymentAction::class)->handle([
        'invoice_ids' => [$invoice->id],
        'amount_paid' => 20000,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
        'school_account_id' => $account->id,
    ]);

    Notification::assertSentTimes(SalaryPaymentReceivedNotification::class, 3);
    Notification::assertSentTo($admin, SalaryPaymentReceivedNotification::class);
    Notification::assertSentTo($superAdmin, SalaryPaymentReceivedNotification::class);
    Notification::assertSentTo($teacher->user, SalaryPaymentReceivedNotification::class);
});

it('stores a database notification with the salary payment details', function () {
    createSalaryNotificationTestAdmins();
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 100000]);
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);
    $invoice = createSalaryNotificationTestInvoice($teacher, 15000);

    app(ProcessIndividualSalaryPaymentAction::class)->handle([
        'invoice_ids' => [$invoice->id],
        'amount_paid' => 15000,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
        'school_account_id' => $account->id,
    ]);

    $notification = $teacher->user->notifications()->latest()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['format'])->toBe('filament')
        ->and($notification->data['title'])->toBe('Salary Payment Made')
        ->and($notification->data['body'])->toContain($teacher->user->name)
        ->and($notification->data['invoice_no'])->toBe($invoice->invoice_no)
        ->and((float) $notification->data['amount_paid'])->toBe(15000.0);
});

it('is queryable by the Filament database notifications bell for the paid teacher', function () {
    createSalaryNotificationTestAdmins();
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 100000]);
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);
    $invoice = createSalaryNotificationTestInvoice($teacher, 15000);

    app(ProcessIndividualSalaryPaymentAction::class)->handle([
        'invoice_ids' => [$invoice->id],
        'amount_paid' => 15000,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
        'school_account_id' => $account->id,
    ]);

    $count = $teacher->user->notifications()->where('data->format', 'filament')->count();

    expect($count)->toBe(1);
});

it('links the admin/super-admin view action to the salary invoice resource, and the teacher to their My Salary page', function () {
    [$admin, $superAdmin] = createSalaryNotificationTestAdmins();
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 100000]);
    $teacher = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);
    $invoice = createSalaryNotificationTestInvoice($teacher, 15000);

    app(ProcessIndividualSalaryPaymentAction::class)->handle([
        'invoice_ids' => [$invoice->id],
        'amount_paid' => 15000,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
        'school_account_id' => $account->id,
    ]);

    $adminAction = $admin->notifications()->latest()->first()->data['actions'][0];
    $superAdminAction = $superAdmin->notifications()->latest()->first()->data['actions'][0];
    $teacherAction = $teacher->user->notifications()->latest()->first()->data['actions'][0];

    expect($adminAction['url'])->toContain('/admin/salary-invoices')
        ->and($adminAction['url'])->toContain('tableAction=view')
        ->and($adminAction['url'])->toContain('tableActionRecord='.$invoice->id)
        ->and($superAdminAction['url'])->toContain('/admin/salary-invoices')
        ->and($teacherAction['url'])->toContain('/teacher/my-salary')
        ->and($teacherAction['url'])->not->toContain('tableActionRecord');
});

it('notifies admins, super-admins, and each paid teacher for a payroll batch run', function () {
    Notification::fake();

    [$admin, $superAdmin] = createSalaryNotificationTestAdmins();
    $account = SchoolAccount::create(['name' => 'Main Fund', 'current_balance' => 100000]);

    $teacherA = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);
    $teacherB = TeacherProfile::factory()->create(['status' => EmploymentStatus::Active]);
    $invoiceA = createSalaryNotificationTestInvoice($teacherA, 20000);
    $invoiceB = createSalaryNotificationTestInvoice($teacherB, 15000);

    app(ProcessPayrollBatchPaymentAction::class)->handle([
        'invoice_ids' => [$invoiceA->id, $invoiceB->id],
        'payment_method' => PaymentMethod::BankTransfer,
        'payment_date' => now()->toDateString(),
        'school_account_id' => $account->id,
    ]);

    Notification::assertSentTimes(SalaryPaymentReceivedNotification::class, 6);
    Notification::assertSentTo($admin, SalaryPaymentReceivedNotification::class);
    Notification::assertSentTo($superAdmin, SalaryPaymentReceivedNotification::class);
    Notification::assertSentTo($teacherA->user, SalaryPaymentReceivedNotification::class);
    Notification::assertSentTo($teacherB->user, SalaryPaymentReceivedNotification::class);
});
