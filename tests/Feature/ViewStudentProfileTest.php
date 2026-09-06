<?php

use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentProfiles\StudentProfileResource;
use App\Models\Classes;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the student view page with profile details and fee summary', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $class = Classes::create(['name' => 'Class Eight', 'order' => 8]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);

    $student = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true, 'name' => 'View Test Student'])->id,
        'roll_no' => 5,
        'registration_no' => 'REG-9001',
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'month' => 3,
        'year' => now()->year,
        'original_amount' => 500,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 500,
        'status' => InvoiceStatus::Unpaid,
    ]);

    $response = $this->actingAs($admin)->get(StudentProfileResource::getUrl('view', ['record' => $student]));

    $response->assertOk()
        ->assertSee('View Test Student')
        ->assertSee('REG-9001')
        ->assertSee('Class Eight')
        ->assertSee('Basic Info')
        ->assertSee('Guardian Info')
        ->assertSee('Fee Summary')
        ->assertSee('Back')
        ->assertSee(StudentProfileResource::getUrl(), false);
});

it('lists recent payments in a table', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $class = Classes::create(['name' => 'Class Ten', 'order' => 10]);
    $feeType = FeeType::create(['name' => 'Exam Fee', 'is_monthly' => false, 'is_active' => true]);

    $student = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 6,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $invoice = StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'year' => now()->year,
        'original_amount' => 300,
        'discount_amount' => 0,
        'fine_amount' => 0,
        'waiver_amount' => 0,
        'net_amount' => 300,
        'status' => InvoiceStatus::Paid,
    ]);

    FeePayment::create([
        'receipt_no' => 'RCPT-TABLE-1',
        'student_id' => $student->id,
        'invoice_id' => $invoice->id,
        'amount_paid' => 300,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($admin)->get(StudentProfileResource::getUrl('view', ['record' => $student]));

    $response->assertOk()
        ->assertSee('<table', false)
        ->assertSee('RCPT-TABLE-1')
        ->assertSee('Exam Fee');
});

it('picks one of the five header gradients based on the student id', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $class = Classes::create(['name' => 'Class Nine', 'order' => 9]);

    $gradientColors = ['primary', 'success', 'warning', 'danger', 'info'];

    foreach (range(1, 5) as $i) {
        $student = StudentProfile::create([
            'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
            'roll_no' => $i,
            'current_class_id' => $class->id,
            'session_year' => now()->year,
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);

        $response = $this->actingAs($admin)->get(StudentProfileResource::getUrl('view', ['record' => $student]));

        $expectedColor = $gradientColors[$student->id % count($gradientColors)];

        $response->assertOk()->assertSee("from-{$expectedColor}-600", false);
    }
});
