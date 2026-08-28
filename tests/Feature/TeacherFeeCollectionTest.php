<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Teacher\Resources\FeePayments\Pages\CreateFeePayment;
use App\Filament\Teacher\Resources\FeePayments\Pages\EditFeePayment;
use App\Filament\Teacher\Resources\FeePayments\Pages\ListFeePayments;
use App\Filament\Teacher\Resources\FeePayments\Pages\ManageClassFeePayments;
use App\Filament\Teacher\Resources\StudentFeeInvoices\Pages\ListStudentFeeInvoices;
use App\Filament\Teacher\Resources\StudentFeeInvoices\Pages\ManageClassStudentFeeInvoices;
use App\Models\Classes;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\Permission;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createFeeTestClass(?TeacherProfile $classTeacher = null): Classes
{
    return Classes::create([
        'name' => 'Class '.str()->random(4),
        'order' => 1,
        'is_active' => true,
        'class_teacher_id' => $classTeacher?->id,
    ]);
}

function createFeeTestTeacher(string $name): TeacherProfile
{
    $teacher = TeacherProfile::create([
        'user_id' => User::factory()->create(['name' => $name, 'user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

    foreach (['view_student_fee_invoices', 'view_fee_payments', 'create_fee_payments', 'edit_fee_payments', 'delete_fee_payments'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $teacher->user->assignRole('teacher');
    $teacher->user->givePermissionTo(['view_student_fee_invoices', 'view_fee_payments', 'create_fee_payments', 'edit_fee_payments', 'delete_fee_payments']);

    return $teacher;
}

function createFeeTestStudent(Classes $class, int $rollNo): StudentProfile
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

function createFeeTestInvoice(StudentProfile $student, float $amount = 1000): StudentFeeInvoice
{
    $feeType = FeeType::create(['name' => 'Tuition '.str()->random(4), 'is_active' => true]);

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
        'status' => InvoiceStatus::Unpaid,
    ]);
}

it('only shows a class card for the teacher\'s own class-teacher class', function () {
    $teacher = createFeeTestTeacher('Own Teacher');
    $ownClass = createFeeTestClass($teacher);
    $otherClass = createFeeTestClass();

    test()->actingAs($teacher->user);

    Livewire::test(ListStudentFeeInvoices::class)
        ->assertSee($ownClass->name)
        ->assertDontSee($otherClass->name);

    Livewire::test(ListFeePayments::class)
        ->assertSee($ownClass->name)
        ->assertDontSee($otherClass->name);
});

it('groups fee invoices by student, scoped to the teacher\'s own class-teacher class', function () {
    $teacher = createFeeTestTeacher('Own Teacher');
    $ownClass = createFeeTestClass($teacher);
    $otherClass = createFeeTestClass();

    $ownStudent = createFeeTestStudent($ownClass, 1);
    $otherStudent = createFeeTestStudent($otherClass, 1);

    createFeeTestInvoice($ownStudent, 1000);
    createFeeTestInvoice($ownStudent, 500);
    createFeeTestInvoice($otherStudent);

    test()->actingAs($teacher->user);

    Livewire::test(ManageClassStudentFeeInvoices::class, ['classId' => $ownClass->id])
        ->assertCanSeeTableRecords([$ownStudent])
        ->assertCanNotSeeTableRecords([$otherStudent])
        ->assertSee('1,500.00');
});

it('blocks a teacher from viewing another class\'s grouped invoices via a tampered class param', function () {
    $teacher = createFeeTestTeacher('Own Teacher');
    createFeeTestClass($teacher);
    $otherClass = createFeeTestClass();

    test()->actingAs($teacher->user);

    $response = test()->get('/teacher/student-fee-invoices/class-invoices?class='.$otherClass->id);

    $response->assertForbidden();
});

it('only lists fee payments for students in the teacher\'s own class-teacher class', function () {
    $teacher = createFeeTestTeacher('Own Teacher');
    $ownClass = createFeeTestClass($teacher);
    $otherClass = createFeeTestClass();

    $ownStudent = createFeeTestStudent($ownClass, 1);
    $otherStudent = createFeeTestStudent($otherClass, 1);

    FeePayment::create([
        'receipt_no' => 'RCP-OWN',
        'student_id' => $ownStudent->id,
        'invoice_id' => createFeeTestInvoice($ownStudent)->id,
        'amount_paid' => 500,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => today(),
    ]);

    FeePayment::create([
        'receipt_no' => 'RCP-OTHER',
        'student_id' => $otherStudent->id,
        'invoice_id' => createFeeTestInvoice($otherStudent)->id,
        'amount_paid' => 500,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => today(),
    ]);

    test()->actingAs($teacher->user);

    Livewire::test(ManageClassFeePayments::class, ['classId' => $ownClass->id])
        ->assertSee('RCP-OWN');

    Livewire::test(ManageClassFeePayments::class, ['classId' => $otherClass->id])
        ->assertDontSee('RCP-OTHER');
});

it('lets a class teacher collect a fee payment for their own class student', function () {
    $teacher = createFeeTestTeacher('Own Teacher');
    $class = createFeeTestClass($teacher);
    $student = createFeeTestStudent($class, 1);
    $invoice = createFeeTestInvoice($student, 1000);

    test()->actingAs($teacher->user);

    Livewire::test(CreateFeePayment::class)
        ->fillForm([
            'receipt_no' => 'RCP-TEST-1',
            'payment_date' => today()->toDateString(),
            'student_id' => $student->id,
            'invoice_ids' => [$invoice->id],
            'amount_paid' => 1000,
            'payment_method' => PaymentMethod::Cash->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(FeePayment::where('receipt_no', 'RCP-TEST-1')->exists())->toBeTrue()
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('blocks a teacher from collecting payment for a student outside their class-teacher class', function () {
    $teacher = createFeeTestTeacher('Own Teacher');
    createFeeTestClass($teacher);
    $otherClass = createFeeTestClass();
    $otherStudent = createFeeTestStudent($otherClass, 1);
    $invoice = createFeeTestInvoice($otherStudent, 1000);

    test()->actingAs($teacher->user);

    Livewire::test(CreateFeePayment::class)
        ->fillForm([
            'receipt_no' => 'RCP-TEST-2',
            'payment_date' => today()->toDateString(),
            'student_id' => $otherStudent->id,
            'invoice_ids' => [$invoice->id],
            'amount_paid' => 1000,
            'payment_method' => PaymentMethod::Cash->value,
        ])
        ->call('create');

    expect(FeePayment::where('receipt_no', 'RCP-TEST-2')->exists())->toBeFalse()
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('hides the Collect Fee header action when the teacher lacks the create_fee_payments permission', function () {
    $teacher = createFeeTestTeacher('Own Teacher');
    $teacher->user->revokePermissionTo('create_fee_payments');
    createFeeTestClass($teacher);

    test()->actingAs($teacher->user);

    Livewire::test(ListFeePayments::class)
        ->assertActionHidden('create');
});

it('shows the Collect Fee header action when the teacher has the create_fee_payments permission', function () {
    $teacher = createFeeTestTeacher('Own Teacher');
    $class = createFeeTestClass($teacher);

    test()->actingAs($teacher->user);

    Livewire::test(ListFeePayments::class)
        ->assertActionVisible('create');

    Livewire::test(ManageClassFeePayments::class, ['classId' => $class->id])
        ->assertActionVisible('create');
});

it('hides the class-scoped Collect Fee header action when the teacher lacks the create_fee_payments permission', function () {
    $teacher = createFeeTestTeacher('Own Teacher');
    $teacher->user->revokePermissionTo('create_fee_payments');
    $class = createFeeTestClass($teacher);

    test()->actingAs($teacher->user);

    Livewire::test(ManageClassFeePayments::class, ['classId' => $class->id])
        ->assertActionHidden('create');
});

it('lets a teacher with edit_fee_payments permission update a payment for their own class-teacher student', function () {
    $teacher = createFeeTestTeacher('Own Teacher');
    $class = createFeeTestClass($teacher);
    $student = createFeeTestStudent($class, 1);
    $invoice = createFeeTestInvoice($student, 1000);

    $payment = FeePayment::create([
        'receipt_no' => 'RCP-EDIT-1',
        'student_id' => $student->id,
        'invoice_id' => $invoice->id,
        'amount_paid' => 500,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => today(),
        'remarks' => 'Original remarks',
    ]);

    test()->actingAs($teacher->user);

    Livewire::test(EditFeePayment::class, ['record' => $payment->id])
        ->fillForm(['remarks' => 'Updated remarks'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($payment->fresh()->remarks)->toBe('Updated remarks');
});

it('shows the Edit action in the fee payments table when the teacher has edit_fee_payments permission', function () {
    $teacher = createFeeTestTeacher('Own Teacher');
    $class = createFeeTestClass($teacher);
    $student = createFeeTestStudent($class, 1);
    $invoice = createFeeTestInvoice($student, 1000);

    $payment = FeePayment::create([
        'receipt_no' => 'RCP-EDIT-2',
        'student_id' => $student->id,
        'invoice_id' => $invoice->id,
        'amount_paid' => 500,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => today(),
    ]);

    test()->actingAs($teacher->user);

    Livewire::test(ManageClassFeePayments::class, ['classId' => $class->id])
        ->assertActionVisible(TestAction::make('edit')->table($payment));
});

it('hides the Edit action and blocks the edit page when the teacher lacks edit_fee_payments permission', function () {
    $teacher = createFeeTestTeacher('Own Teacher');
    $teacher->user->revokePermissionTo('edit_fee_payments');
    $class = createFeeTestClass($teacher);
    $student = createFeeTestStudent($class, 1);
    $invoice = createFeeTestInvoice($student, 1000);

    $payment = FeePayment::create([
        'receipt_no' => 'RCP-EDIT-3',
        'student_id' => $student->id,
        'invoice_id' => $invoice->id,
        'amount_paid' => 500,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => today(),
    ]);

    test()->actingAs($teacher->user);

    Livewire::test(ManageClassFeePayments::class, ['classId' => $class->id])
        ->assertActionHidden(TestAction::make('edit')->table($payment));

    $response = test()->get(route('filament.teacher.resources.fee-payments.edit', ['record' => $payment->id]));

    $response->assertForbidden();
});

it('blocks a teacher from editing a fee payment for a student outside their class-teacher class', function () {
    $teacher = createFeeTestTeacher('Own Teacher');
    createFeeTestClass($teacher);
    $otherClass = createFeeTestClass();
    $otherStudent = createFeeTestStudent($otherClass, 1);
    $invoice = createFeeTestInvoice($otherStudent, 1000);

    $payment = FeePayment::create([
        'receipt_no' => 'RCP-EDIT-4',
        'student_id' => $otherStudent->id,
        'invoice_id' => $invoice->id,
        'amount_paid' => 500,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => today(),
    ]);

    test()->actingAs($teacher->user);

    $response = test()->get(route('filament.teacher.resources.fee-payments.edit', ['record' => $payment->id]));

    $response->assertNotFound();
});

it('lets a teacher with delete_fee_payments permission delete a payment for their own class-teacher student', function () {
    $teacher = createFeeTestTeacher('Own Teacher');
    $class = createFeeTestClass($teacher);
    $student = createFeeTestStudent($class, 1);
    $invoice = createFeeTestInvoice($student, 1000);

    $payment = FeePayment::create([
        'receipt_no' => 'RCP-DELETE-1',
        'student_id' => $student->id,
        'invoice_id' => $invoice->id,
        'amount_paid' => 500,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => today(),
    ]);

    test()->actingAs($teacher->user);

    Livewire::test(ManageClassFeePayments::class, ['classId' => $class->id])
        ->callAction(TestAction::make('delete')->table($payment));

    expect(FeePayment::find($payment->id))->toBeNull();
});

it('shows the Delete action in the fee payments table when the teacher has delete_fee_payments permission', function () {
    $teacher = createFeeTestTeacher('Own Teacher');
    $class = createFeeTestClass($teacher);
    $student = createFeeTestStudent($class, 1);
    $invoice = createFeeTestInvoice($student, 1000);

    $payment = FeePayment::create([
        'receipt_no' => 'RCP-DELETE-2',
        'student_id' => $student->id,
        'invoice_id' => $invoice->id,
        'amount_paid' => 500,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => today(),
    ]);

    test()->actingAs($teacher->user);

    Livewire::test(ManageClassFeePayments::class, ['classId' => $class->id])
        ->assertActionVisible(TestAction::make('delete')->table($payment));
});

it('hides the Delete action in the fee payments table when the teacher lacks delete_fee_payments permission', function () {
    $teacher = createFeeTestTeacher('Own Teacher');
    $teacher->user->revokePermissionTo('delete_fee_payments');
    $class = createFeeTestClass($teacher);
    $student = createFeeTestStudent($class, 1);
    $invoice = createFeeTestInvoice($student, 1000);

    $payment = FeePayment::create([
        'receipt_no' => 'RCP-DELETE-3',
        'student_id' => $student->id,
        'invoice_id' => $invoice->id,
        'amount_paid' => 500,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => today(),
    ]);

    test()->actingAs($teacher->user);

    Livewire::test(ManageClassFeePayments::class, ['classId' => $class->id])
        ->assertActionHidden(TestAction::make('delete')->table($payment));

    expect(FeePayment::find($payment->id))->not->toBeNull();
});

it('does not let a teacher without fee permissions access the fee invoices page', function () {
    $teacher = TeacherProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    test()->actingAs($teacher->user);

    $response = test()->get('/teacher/student-fee-invoices');

    $response->assertForbidden();
});
