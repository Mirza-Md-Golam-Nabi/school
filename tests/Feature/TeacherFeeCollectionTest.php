<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Teacher\Resources\FeePayments\Pages\CreateFeePayment;
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

    foreach (['view_student_fee_invoices', 'view_fee_payments', 'create_fee_payments'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $teacher->user->assignRole('teacher');
    $teacher->user->givePermissionTo(['view_student_fee_invoices', 'view_fee_payments', 'create_fee_payments']);

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
