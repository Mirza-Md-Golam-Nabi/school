<?php

use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentFeeInvoices\Pages\EditStudentFeeInvoice;
use App\Models\Classes;
use App\Models\FeeType;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('prefills the class filter on the invoice edit page from the invoice\'s student', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 7', 'order' => 7, 'is_active' => true]);
    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);

    $student = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

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

    Livewire::test(EditStudentFeeInvoice::class, ['record' => $invoice->id])
        ->assertSchemaStateSet([
            'class_id_filter' => $class->id,
            'student_id' => $student->id,
        ]);
});
