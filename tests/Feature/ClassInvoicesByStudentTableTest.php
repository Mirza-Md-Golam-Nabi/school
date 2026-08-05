<?php

use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentFeeInvoices\Pages\ManageClassStudentFeeInvoices;
use App\Models\Classes;
use App\Models\FeeType;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createGroupedInvoiceTestStudent(Classes $class, int $rollNo): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create()->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function createGroupedInvoiceTestInvoice(StudentProfile $student, float $amount, InvoiceStatus $status = InvoiceStatus::Unpaid): StudentFeeInvoice
{
    $feeType = FeeType::create(['name' => 'Fee '.str()->random(4), 'is_active' => true]);

    return StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => now()->month,
        'year' => now()->year,
        'original_amount' => $amount,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => $amount,
        'status' => $status,
    ]);
}

it('shows one row per student with the total due summed across their invoices', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Class Fee Group', 'order' => 1]);

    $student = createGroupedInvoiceTestStudent($class, 1);
    createGroupedInvoiceTestInvoice($student, 1000);
    createGroupedInvoiceTestInvoice($student, 750);
    createGroupedInvoiceTestInvoice($student, 500, InvoiceStatus::Paid);

    test()->actingAs($admin);

    Livewire::test(ManageClassStudentFeeInvoices::class, ['classId' => $class->id])
        ->assertCanSeeTableRecords([$student])
        ->assertSee($student->user->name)
        ->assertSee('1,750.00')
        ->assertDontSee('500.00');
});

it('excludes a student whose invoices are all already paid', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Class Fee Group Paid', 'order' => 4]);

    $clearedStudent = createGroupedInvoiceTestStudent($class, 1);
    createGroupedInvoiceTestInvoice($clearedStudent, 300, InvoiceStatus::Paid);

    $owingStudent = createGroupedInvoiceTestStudent($class, 2);
    createGroupedInvoiceTestInvoice($owingStudent, 300, InvoiceStatus::Unpaid);

    test()->actingAs($admin);

    Livewire::test(ManageClassStudentFeeInvoices::class, ['classId' => $class->id])
        ->assertCanSeeTableRecords([$owingStudent])
        ->assertCanNotSeeTableRecords([$clearedStudent])
        ->assertCountTableRecords(1);
});

it('counts only pending invoices in the Pending Invoices column', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Class Fee Group Count', 'order' => 5]);

    $student = createGroupedInvoiceTestStudent($class, 1);
    createGroupedInvoiceTestInvoice($student, 100, InvoiceStatus::Unpaid);
    createGroupedInvoiceTestInvoice($student, 100, InvoiceStatus::Partial);
    createGroupedInvoiceTestInvoice($student, 100, InvoiceStatus::Paid);
    createGroupedInvoiceTestInvoice($student, 100, InvoiceStatus::Waived);

    test()->actingAs($admin);

    // 2 pending (unpaid + partial), not the 4 total invoices created.
    Livewire::test(ManageClassStudentFeeInvoices::class, ['classId' => $class->id])
        ->assertTableColumnStateSet('invoice_count', 2, record: $student);
});

it('does not repeat a student\'s row for each of their invoices', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Class Fee Group Two', 'order' => 2]);

    $student = createGroupedInvoiceTestStudent($class, 1);
    createGroupedInvoiceTestInvoice($student, 300);
    createGroupedInvoiceTestInvoice($student, 300);
    createGroupedInvoiceTestInvoice($student, 300);

    test()->actingAs($admin);

    Livewire::test(ManageClassStudentFeeInvoices::class, ['classId' => $class->id])
        ->assertCountTableRecords(1);
});

it('shows all pending invoices for a student in the view action modal', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Class Fee Group Three', 'order' => 3]);

    $student = createGroupedInvoiceTestStudent($class, 1);
    $unpaid = createGroupedInvoiceTestInvoice($student, 400, InvoiceStatus::Unpaid);
    $paid = createGroupedInvoiceTestInvoice($student, 200, InvoiceStatus::Paid);

    test()->actingAs($admin);

    Livewire::test(ManageClassStudentFeeInvoices::class, ['classId' => $class->id])
        ->mountAction(
            TestAction::make('viewPending')->table($student),
        )
        ->assertMountedActionModalSee($unpaid->feeType->name)
        ->assertMountedActionModalDontSee($paid->feeType->name);
});
