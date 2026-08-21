<?php

use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentFeeInvoices\Pages\CreateStudentFeeInvoice as AdminCreateStudentFeeInvoice;
use App\Filament\Resources\StudentFeeInvoices\Pages\EditStudentFeeInvoice as AdminEditStudentFeeInvoice;
use App\Filament\Teacher\Resources\StudentFeeInvoices\Pages\CreateStudentFeeInvoice as TeacherCreateStudentFeeInvoice;
use App\Filament\Teacher\Resources\StudentFeeInvoices\Pages\EditStudentFeeInvoice as TeacherEditStudentFeeInvoice;
use App\Models\Classes;
use App\Models\FeeType;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\StudentFeeInvoiceGeneratedNotification;
use App\Notifications\StudentFeeInvoiceUpdatedNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createFeeInvoiceNotificationTestStudent(Classes $class): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it('notifies the student when an admin manually creates a fee invoice', function () {
    Notification::fake();
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 9', 'order' => 9, 'is_active' => true]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $student = createFeeInvoiceNotificationTestStudent($class);

    Livewire::test(AdminCreateStudentFeeInvoice::class)
        ->fillForm([
            'student_id' => $student->id,
            'fee_type_id' => $feeType->id,
            'month' => 8,
            'year' => now()->year,
            'original_amount' => 500,
            'discount_amount' => 0,
            'fine_amount' => 0,
            'waiver_amount' => 0,
            'net_amount' => 500,
            'status' => InvoiceStatus::Unpaid->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($student->user, StudentFeeInvoiceGeneratedNotification::class);
    Notification::assertNotSentTo($student->user, StudentFeeInvoiceUpdatedNotification::class);
});

it('notifies the student when an admin updates a fee invoice', function () {
    Notification::fake();
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 9', 'order' => 9, 'is_active' => true]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $student = createFeeInvoiceNotificationTestStudent($class);

    $invoice = StudentFeeInvoice::create([
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

    Livewire::test(AdminEditStudentFeeInvoice::class, ['record' => $invoice->id])
        ->fillForm(['fine_amount' => 50, 'net_amount' => 550])
        ->call('save')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($student->user, StudentFeeInvoiceUpdatedNotification::class);
    Notification::assertNotSentTo($student->user, StudentFeeInvoiceGeneratedNotification::class);
});

it('notifies the student when a teacher manually creates a fee invoice for their own class', function () {
    Notification::fake();

    $teacher = createInvoiceTestTeacher('Fee Notify Teacher');
    $class = createInvoiceTestClass($teacher);
    $student = createInvoiceTestStudent($class, 1);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_active' => true]);

    test()->actingAs($teacher->user);

    Livewire::test(TeacherCreateStudentFeeInvoice::class)
        ->fillForm([
            'student_id' => $student->id,
            'fee_type_id' => $feeType->id,
            'month' => 8,
            'year' => now()->year,
            'original_amount' => 500,
            'discount_amount' => 0,
            'fine_amount' => 0,
            'waiver_amount' => 0,
            'net_amount' => 500,
            'status' => InvoiceStatus::Unpaid->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($student->user, StudentFeeInvoiceGeneratedNotification::class);
});

it('notifies the student when a teacher updates a fee invoice for their own class', function () {
    Notification::fake();

    $teacher = createInvoiceTestTeacher('Fee Notify Teacher 2');
    $class = createInvoiceTestClass($teacher);
    $student = createInvoiceTestStudent($class, 1);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_active' => true]);

    $invoice = StudentFeeInvoice::create([
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

    test()->actingAs($teacher->user);

    Livewire::test(TeacherEditStudentFeeInvoice::class, ['record' => $invoice->id])
        ->fillForm(['fine_amount' => 25, 'net_amount' => 525])
        ->call('save')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($student->user, StudentFeeInvoiceUpdatedNotification::class);
});
