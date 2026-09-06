<?php

use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\FeePayments\FeePaymentResource;
use App\Filament\Resources\StudentFeeInvoices\Pages\ManageClassStudentFeeInvoices;
use App\Models\Classes;
use App\Models\FeeType;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('links the collect payment row action to the fee-payments create page with class and student pre-selected', function () {
    Filament::setCurrentPanel('admin');

    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

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

    $expectedUrl = FeePaymentResource::getUrl('create', [
        'class_id' => $class->id,
        'student_id' => $student->id,
    ]);

    $html = Livewire::test(ManageClassStudentFeeInvoices::class, ['classId' => $class->id])->html();

    expect(html_entity_decode($html))->toContain($expectedUrl);
});

it('pre-fills the class and student selects on the fee-payments create page from query parameters', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $class = Classes::create(['name' => 'Class Seven', 'order' => 7]);
    $student = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 3,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $url = FeePaymentResource::getUrl('create', [
        'class_id' => $class->id,
        'student_id' => $student->id,
    ]);

    $response = $this->actingAs($admin)->get($url);

    $response->assertOk()->assertSee($student->user->name);
});
