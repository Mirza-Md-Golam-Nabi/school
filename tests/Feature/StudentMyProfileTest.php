<?php

use App\Enums\AddressType;
use App\Enums\FeeDiscountType;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Student\Pages\MyProfile;
use App\Models\Classes;
use App\Models\FeeDiscount;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\StudentFeeDiscount;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the logged-in student their own basic, guardian, and fee info across tabs', function () {
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true, 'name' => 'Rahim Uddin']);

    $class = Classes::create(['name' => 'Class Eight', 'order' => 8, 'is_active' => true]);

    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 12,
        'registration_no' => 5501,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
        'father_name' => 'Karim Uddin',
        'mother_name' => 'Amena Begum',
        'guardian_name' => 'Karim Uddin',
        'guardian_relation' => 'Father',
    ]);

    $student->addresses()->create([
        'type' => AddressType::Present,
        'address' => 'House 12, Road 4, Dhaka',
        'is_same' => true,
    ]);

    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);

    FeeStructure::create([
        'class_id' => $class->id,
        'fee_type_id' => $feeType->id,
        'amount' => 1500,
        'due_day' => 10,
        'session_year' => now()->year,
        'is_active' => true,
    ]);

    $discount = FeeDiscount::create([
        'name' => 'Merit Scholarship',
        'discount_type' => FeeDiscountType::Percent,
        'discount_value' => 20,
    ]);

    StudentFeeDiscount::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'discount_id' => $discount->id,
        'session_year' => now()->year,
    ]);

    $response = $this->actingAs($user)->get(MyProfile::getUrl(panel: 'student'));

    $response->assertOk()
        ->assertSee('Rahim Uddin')
        ->assertSee('12') // roll no
        ->assertSee('Class Eight')
        ->assertSee('Karim Uddin')
        ->assertSee('Amena Begum')
        ->assertSee('House 12, Road 4, Dhaka')
        ->assertSee('Tuition Fee')
        ->assertSee('Merit Scholarship')
        ->assertSee('৳300.0'); // 20% of the ৳1500 fee structure amount
});

it('shows a fixed-amount discount as-is in taka', function () {
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    $class = Classes::create(['name' => 'Class Nine', 'order' => 9, 'is_active' => true]);

    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 7,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Female,
        'status' => StudentStatus::Active,
    ]);

    $feeType = FeeType::create(['name' => 'Exam Fee', 'is_monthly' => false, 'is_active' => true]);

    FeeStructure::create([
        'class_id' => $class->id,
        'fee_type_id' => $feeType->id,
        'amount' => 800,
        'due_day' => 10,
        'session_year' => now()->year,
        'is_active' => true,
    ]);

    $discount = FeeDiscount::create([
        'name' => 'Staff Ward Discount',
        'discount_type' => FeeDiscountType::Fixed,
        'discount_value' => 150.50,
    ]);

    StudentFeeDiscount::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'discount_id' => $discount->id,
        'session_year' => now()->year,
    ]);

    $response = $this->actingAs($user)->get(MyProfile::getUrl(panel: 'student'));

    $response->assertOk()
        ->assertSee('Staff Ward Discount')
        ->assertSee('৳150.5');
});

it('does not error when a student has no profile yet', function () {
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    $response = $this->actingAs($user)->get(MyProfile::getUrl(panel: 'student'));

    $response->assertOk()->assertSee('No profile information found.');
});
