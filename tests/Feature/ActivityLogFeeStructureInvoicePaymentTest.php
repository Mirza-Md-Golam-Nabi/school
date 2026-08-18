<?php

use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('logs create, update, and delete of a fee structure with class and fee-type labels', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 8', 'order' => 8]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);

    $structure = FeeStructure::create([
        'class_id' => $class->id,
        'fee_type_id' => $feeType->id,
        'amount' => 500,
        'due_day' => 10,
        'session_year' => 2026,
        'is_active' => true,
    ]);

    $createdActivity = Activity::where('log_name', 'fee_structure')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->description->toBe('Created fee structure for "Tuition Fee" - Class 8 (2026).');

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'class_id' => $class->id,
            'class_id_label' => 'Class 8',
            'fee_type_id' => $feeType->id,
            'fee_type_id_label' => 'Tuition Fee',
        ]);

    $structure->update(['amount' => 600]);

    expect(Activity::where('log_name', 'fee_structure')->where('event', 'updated')->first())
        ->not->toBeNull()
        ->description->toBe('Updated fee structure for "Tuition Fee" - Class 8 (2026).');

    $structure->delete();

    expect(Activity::where('log_name', 'fee_structure')->where('event', 'deleted')->first())
        ->not->toBeNull()
        ->description->toBe('Deleted fee structure for "Tuition Fee" - Class 8 (2026).');
});

it('logs create, update, and delete of a fee invoice with student, fee-type, and period in the description', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 9', 'order' => 9]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $user = User::factory()->create(['name' => 'Karim Hossain', 'user_type' => UserType::Student, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 5,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $invoice = StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => 8,
        'year' => 2026,
        'original_amount' => 500,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 500,
        'status' => InvoiceStatus::Unpaid,
    ]);

    $createdActivity = Activity::where('log_name', 'student_fee_invoice')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->description->toBe('Created fee invoice for "Class 9 - Karim Hossain (Roll: 5)" - Tuition Fee (August 2026).');

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'student_id' => $student->id,
            'student_id_label' => 'Class 9 - Karim Hossain (Roll: 5)',
            'fee_type_id' => $feeType->id,
            'fee_type_id_label' => 'Tuition Fee',
            'status' => 'unpaid',
            'status_label' => 'Unpaid',
        ]);

    $invoice->update(['status' => InvoiceStatus::Partial]);

    expect(Activity::where('log_name', 'student_fee_invoice')->where('event', 'updated')->first())
        ->not->toBeNull()
        ->description->toBe('Updated fee invoice for "Class 9 - Karim Hossain (Roll: 5)" - Tuition Fee (August 2026).');

    $invoice->delete();

    expect(Activity::where('log_name', 'student_fee_invoice')->where('event', 'deleted')->first())
        ->not->toBeNull()
        ->description->toBe('Deleted fee invoice for "Class 9 - Karim Hossain (Roll: 5)" - Tuition Fee (August 2026).');
});

it('omits the class segment in the fee invoice description when the student has no class assigned', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $feeType = FeeType::create(['name' => 'Admission Fee', 'is_monthly' => false, 'is_active' => true]);
    $user = User::factory()->create(['name' => 'Fatema Akter', 'user_type' => UserType::Student, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 9,
        'session_year' => now()->year,
        'gender' => Gender::Female,
        'status' => StudentStatus::Active,
    ]);

    StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'year' => 2026,
        'original_amount' => 1000,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 1000,
        'status' => InvoiceStatus::Unpaid,
    ]);

    $createdActivity = Activity::where('log_name', 'student_fee_invoice')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->description->toBe('Created fee invoice for "Fatema Akter (Roll: 9)" - Admission Fee (2026).');
});

it('logs create, update, and delete of a fee payment with student, invoice, and receipt in the description', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 10', 'order' => 10]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $user = User::factory()->create(['name' => 'Karim Hossain', 'user_type' => UserType::Student, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 5,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $invoice = StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => 8,
        'year' => 2026,
        'original_amount' => 500,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 500,
        'status' => InvoiceStatus::Unpaid,
    ]);

    $payment = FeePayment::create([
        'receipt_no' => 'RCPT-1001',
        'student_id' => $student->id,
        'invoice_id' => $invoice->id,
        'amount_paid' => 500,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
        'received_by' => $admin->id,
    ]);

    $createdActivity = Activity::where('log_name', 'fee_payment')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->description->toBe('Created payment of ৳500.00 for "Class 10 - Karim Hossain (Roll: 5)" - Tuition Fee (August 2026) (Receipt #RCPT-1001).');

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'student_id' => $student->id,
            'student_id_label' => 'Class 10 - Karim Hossain (Roll: 5)',
            'invoice_id' => $invoice->id,
            'invoice_id_label' => 'Tuition Fee (August 2026)',
            'received_by' => $admin->id,
            'received_by_label' => $admin->name,
            'payment_method' => 'cash',
            'payment_method_label' => 'Cash',
        ]);

    $payment->update(['remarks' => 'Paid in full']);

    expect(Activity::where('log_name', 'fee_payment')->where('event', 'updated')->first())
        ->not->toBeNull()
        ->description->toBe('Updated payment of ৳500.00 for "Class 10 - Karim Hossain (Roll: 5)" - Tuition Fee (August 2026) (Receipt #RCPT-1001).');

    $payment->delete();

    expect(Activity::where('log_name', 'fee_payment')->where('event', 'deleted')->first())
        ->not->toBeNull()
        ->description->toBe('Deleted payment of ৳500.00 for "Class 10 - Karim Hossain (Roll: 5)" - Tuition Fee (August 2026) (Receipt #RCPT-1001).');
});

it('omits the class segment in the fee payment description when the student has no class assigned', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $feeType = FeeType::create(['name' => 'Admission Fee', 'is_monthly' => false, 'is_active' => true]);
    $user = User::factory()->create(['name' => 'Fatema Akter', 'user_type' => UserType::Student, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 9,
        'session_year' => now()->year,
        'gender' => Gender::Female,
        'status' => StudentStatus::Active,
    ]);

    $invoice = StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'year' => 2026,
        'original_amount' => 1000,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 1000,
        'status' => InvoiceStatus::Unpaid,
    ]);

    FeePayment::create([
        'receipt_no' => 'RCPT-2002',
        'student_id' => $student->id,
        'invoice_id' => $invoice->id,
        'amount_paid' => 1000,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
        'received_by' => $admin->id,
    ]);

    $createdActivity = Activity::where('log_name', 'fee_payment')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->description->toBe('Created payment of ৳1,000.00 for "Fatema Akter (Roll: 9)" - Admission Fee (2026) (Receipt #RCPT-2002).');
});
