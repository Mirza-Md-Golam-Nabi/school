<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentFeeInvoices\Pages\ManageClassStudentFeeInvoices as AdminManageClassStudentFeeInvoices;
use App\Filament\Teacher\Resources\StudentFeeInvoices\Pages\ManageClassStudentFeeInvoices as TeacherManageClassStudentFeeInvoices;
use App\Models\Classes;
use App\Models\FeeType;
use App\Models\Permission;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function setUpEditLinkTestScenario(): array
{
    $class = Classes::create(['name' => 'Class 6', 'order' => 6, 'is_active' => true]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);

    $student = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => 8,
        'year' => now()->year,
        'original_amount' => 500,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 500,
        'status' => InvoiceStatus::Unpaid,
    ]);

    return compact('class', 'student');
}

it('shows an edit link for a pending invoice on the admin panel', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    ['class' => $class, 'student' => $student] = setUpEditLinkTestScenario();

    Livewire::test(AdminManageClassStudentFeeInvoices::class, ['classId' => $class->id])
        ->mountAction(TestAction::make('viewPending')->table($student))
        ->assertSchemaComponentVisible('pendingInvoices.0.edit');
});

it('hides the edit link for a pending invoice on the teacher panel when the teacher lacks edit_student_fee_invoices permission', function () {
    Filament::setCurrentPanel('teacher');

    ['class' => $class, 'student' => $student] = setUpEditLinkTestScenario();

    $teacher = TeacherProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    $class->update(['class_teacher_id' => $teacher->id]);

    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'view_student_fee_invoices', 'guard_name' => 'web']);

    $teacher->user->assignRole('teacher');
    $teacher->user->givePermissionTo('view_student_fee_invoices');

    test()->actingAs($teacher->user);

    Livewire::test(TeacherManageClassStudentFeeInvoices::class, ['classId' => $class->id])
        ->mountAction(TestAction::make('viewPending')->table($student))
        ->assertSchemaComponentHidden('pendingInvoices.0.edit');
});

it('shows the edit link for a pending invoice on the teacher panel when the teacher has edit_student_fee_invoices permission', function () {
    Filament::setCurrentPanel('teacher');

    ['class' => $class, 'student' => $student] = setUpEditLinkTestScenario();

    $teacher = TeacherProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    $class->update(['class_teacher_id' => $teacher->id]);

    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    foreach (['view_student_fee_invoices', 'edit_student_fee_invoices'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $teacher->user->assignRole('teacher');
    $teacher->user->givePermissionTo(['view_student_fee_invoices', 'edit_student_fee_invoices']);

    test()->actingAs($teacher->user);

    Livewire::test(TeacherManageClassStudentFeeInvoices::class, ['classId' => $class->id])
        ->mountAction(TestAction::make('viewPending')->table($student))
        ->assertSchemaComponentVisible('pendingInvoices.0.edit');
});
