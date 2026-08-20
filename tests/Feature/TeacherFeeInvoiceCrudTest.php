<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Teacher\Resources\StudentFeeInvoices\Pages\CreateStudentFeeInvoice;
use App\Filament\Teacher\Resources\StudentFeeInvoices\Pages\EditStudentFeeInvoice;
use App\Filament\Teacher\Resources\StudentFeeInvoices\Pages\ManageClassStudentFeeInvoices;
use App\Models\Classes;
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

function createInvoiceTestTeacher(string $name, array $extraPermissions = []): TeacherProfile
{
    $teacher = TeacherProfile::create([
        'user_id' => User::factory()->create(['name' => $name, 'user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

    $permissions = array_unique([...['view_student_fee_invoices', 'create_student_fee_invoices', 'edit_student_fee_invoices', 'delete_student_fee_invoices'], ...$extraPermissions]);

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $teacher->user->assignRole('teacher');
    $teacher->user->givePermissionTo($permissions);

    return $teacher;
}

function createInvoiceTestClass(?TeacherProfile $classTeacher = null): Classes
{
    return Classes::create([
        'name' => 'Class '.str()->random(4),
        'order' => 1,
        'is_active' => true,
        'class_teacher_id' => $classTeacher?->id,
    ]);
}

function createInvoiceTestStudent(Classes $class, int $rollNo): StudentProfile
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

it('lets a teacher with create_student_fee_invoices permission create an invoice for their own class-teacher student', function () {
    $teacher = createInvoiceTestTeacher('Own Teacher');
    $class = createInvoiceTestClass($teacher);
    $student = createInvoiceTestStudent($class, 1);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_active' => true]);

    test()->actingAs($teacher->user);

    Livewire::test(CreateStudentFeeInvoice::class)
        ->fillForm([
            'student_id' => $student->id,
            'fee_type_id' => $feeType->id,
            'month' => 8,
            'year' => 2026,
            'original_amount' => 1000,
            'discount_amount' => 0,
            'fine_amount' => 0,
            'waiver_amount' => 0,
            'net_amount' => 1000,
            'status' => InvoiceStatus::Unpaid->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(StudentFeeInvoice::where('student_id', $student->id)->exists())->toBeTrue();
});

it('blocks a teacher from creating an invoice for a student outside their class-teacher class', function () {
    $teacher = createInvoiceTestTeacher('Own Teacher');
    createInvoiceTestClass($teacher);
    $otherClass = createInvoiceTestClass();
    $otherStudent = createInvoiceTestStudent($otherClass, 1);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_active' => true]);

    test()->actingAs($teacher->user);

    Livewire::test(CreateStudentFeeInvoice::class)
        ->fillForm([
            'student_id' => $otherStudent->id,
            'fee_type_id' => $feeType->id,
            'month' => 8,
            'year' => 2026,
            'original_amount' => 1000,
            'discount_amount' => 0,
            'fine_amount' => 0,
            'waiver_amount' => 0,
            'net_amount' => 1000,
            'status' => InvoiceStatus::Unpaid->value,
        ])
        ->call('create');

    expect(StudentFeeInvoice::where('student_id', $otherStudent->id)->exists())->toBeFalse();
});

it('does not let a teacher without create_student_fee_invoices permission access the create page', function () {
    $teacher = createInvoiceTestTeacher('Own Teacher');
    $teacher->user->revokePermissionTo('create_student_fee_invoices');
    createInvoiceTestClass($teacher);

    test()->actingAs($teacher->user);

    $response = test()->get(route('filament.teacher.resources.student-fee-invoices.create'));

    $response->assertForbidden();
});

it('lets a teacher with edit_student_fee_invoices permission update an invoice for their own class-teacher student', function () {
    $teacher = createInvoiceTestTeacher('Own Teacher');
    $class = createInvoiceTestClass($teacher);
    $student = createInvoiceTestStudent($class, 1);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_active' => true]);

    $invoice = StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => 8,
        'year' => 2026,
        'original_amount' => 1000,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 1000,
        'status' => InvoiceStatus::Unpaid,
    ]);

    test()->actingAs($teacher->user);

    Livewire::test(EditStudentFeeInvoice::class, ['record' => $invoice->id])
        ->fillForm(['fine_amount' => 50, 'net_amount' => 1050])
        ->call('save')
        ->assertHasNoFormErrors();

    expect((float) $invoice->fresh()->fine_amount)->toBe(50.0);
});

it('does not let a teacher without edit_student_fee_invoices permission access the edit page', function () {
    $teacher = createInvoiceTestTeacher('Own Teacher');
    $teacher->user->revokePermissionTo('edit_student_fee_invoices');
    $class = createInvoiceTestClass($teacher);
    $student = createInvoiceTestStudent($class, 1);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_active' => true]);

    $invoice = StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => 8,
        'year' => 2026,
        'original_amount' => 1000,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 1000,
        'status' => InvoiceStatus::Unpaid,
    ]);

    test()->actingAs($teacher->user);

    $response = test()->get(route('filament.teacher.resources.student-fee-invoices.edit', ['record' => $invoice->id]));

    $response->assertForbidden();
});

it('blocks a teacher from editing an invoice for a student outside their class-teacher class', function () {
    $teacher = createInvoiceTestTeacher('Own Teacher');
    createInvoiceTestClass($teacher);
    $otherClass = createInvoiceTestClass();
    $otherStudent = createInvoiceTestStudent($otherClass, 1);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_active' => true]);

    $invoice = StudentFeeInvoice::create([
        'student_id' => $otherStudent->id,
        'fee_type_id' => $feeType->id,
        'month' => 8,
        'year' => 2026,
        'original_amount' => 1000,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 1000,
        'status' => InvoiceStatus::Unpaid,
    ]);

    test()->actingAs($teacher->user);

    $response = test()->get(route('filament.teacher.resources.student-fee-invoices.edit', ['record' => $invoice->id]));

    $response->assertNotFound();
});

it('lets a teacher with delete_student_fee_invoices permission delete an invoice via the edit page', function () {
    $teacher = createInvoiceTestTeacher('Own Teacher');
    $class = createInvoiceTestClass($teacher);
    $student = createInvoiceTestStudent($class, 1);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_active' => true]);

    $invoice = StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => 8,
        'year' => 2026,
        'original_amount' => 1000,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 1000,
        'status' => InvoiceStatus::Unpaid,
    ]);

    test()->actingAs($teacher->user);

    Livewire::test(EditStudentFeeInvoice::class, ['record' => $invoice->id])
        ->callAction('delete');

    expect(StudentFeeInvoice::find($invoice->id))->toBeNull();
});

it('hides the Delete action on the edit page when the teacher lacks delete_student_fee_invoices permission', function () {
    $teacher = createInvoiceTestTeacher('Own Teacher');
    $teacher->user->revokePermissionTo('delete_student_fee_invoices');
    $class = createInvoiceTestClass($teacher);
    $student = createInvoiceTestStudent($class, 1);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_active' => true]);

    $invoice = StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => 8,
        'year' => 2026,
        'original_amount' => 1000,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 1000,
        'status' => InvoiceStatus::Unpaid,
    ]);

    test()->actingAs($teacher->user);

    Livewire::test(EditStudentFeeInvoice::class, ['record' => $invoice->id])
        ->assertActionHidden('delete');

    expect(StudentFeeInvoice::find($invoice->id))->not->toBeNull();
});

it('shows the Create Invoice header action on the class-invoices page when the teacher has create_student_fee_invoices permission', function () {
    $teacher = createInvoiceTestTeacher('Own Teacher');
    $class = createInvoiceTestClass($teacher);

    test()->actingAs($teacher->user);

    Livewire::test(ManageClassStudentFeeInvoices::class, ['classId' => $class->id])
        ->assertActionVisible('create');
});

it('hides the Create Invoice header action on the class-invoices page when the teacher lacks create_student_fee_invoices permission', function () {
    $teacher = createInvoiceTestTeacher('Own Teacher');
    $teacher->user->revokePermissionTo('create_student_fee_invoices');
    $class = createInvoiceTestClass($teacher);

    test()->actingAs($teacher->user);

    Livewire::test(ManageClassStudentFeeInvoices::class, ['classId' => $class->id])
        ->assertActionHidden('create');
});
