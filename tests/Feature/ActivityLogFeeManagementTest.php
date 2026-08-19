<?php

use App\Enums\FeeDiscountType;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\FeeDiscount;
use App\Models\FeeType;
use App\Models\SchoolAccount;
use App\Models\StudentFeeDiscount;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('logs create, update, and delete of a school account', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $account = SchoolAccount::create(['name' => 'Main Account', 'current_balance' => 0]);

    $createdActivity = Activity::where('log_name', 'school_account')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->description->toBe('Created school account "Main Account".');

    $account->update(['name' => 'Primary Account']);

    expect(Activity::where('log_name', 'school_account')->where('event', 'updated')->first())
        ->not->toBeNull()
        ->description->toBe('Updated school account "Primary Account".');

    $account->delete();

    expect(Activity::where('log_name', 'school_account')->where('event', 'deleted')->first())
        ->not->toBeNull()
        ->description->toBe('Deleted school account "Primary Account".');
});

it('logs create, update, and delete of a fee type with the school account label', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $account = SchoolAccount::create(['name' => 'Main Account', 'current_balance' => 0]);

    $feeType = FeeType::create([
        'name' => 'Tuition Fee',
        'school_account_id' => $account->id,
        'is_monthly' => true,
        'is_active' => true,
    ]);

    $createdActivity = Activity::where('log_name', 'fee_type')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->description->toBe('Created fee type "Tuition Fee".');

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'school_account_id' => $account->id,
            'school_account_id_label' => 'Main Account',
        ]);

    $feeType->update(['name' => 'Monthly Tuition Fee']);

    expect(Activity::where('log_name', 'fee_type')->where('event', 'updated')->first())
        ->not->toBeNull()
        ->description->toBe('Updated fee type "Monthly Tuition Fee".');

    $feeType->delete();

    expect(Activity::where('log_name', 'fee_type')->where('event', 'deleted')->first())
        ->not->toBeNull()
        ->description->toBe('Deleted fee type "Monthly Tuition Fee".');
});

it('logs create, update, and delete of a fee discount with a readable type label', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $discount = FeeDiscount::create([
        'name' => 'Sibling Discount',
        'discount_type' => FeeDiscountType::Percent,
        'discount_value' => 10,
    ]);

    $createdActivity = Activity::where('log_name', 'fee_discount')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->description->toBe('Created fee discount "Sibling Discount".');

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'discount_type' => 'percent',
            'discount_type_label' => 'Percent (%)',
        ]);

    $discount->update(['discount_type' => FeeDiscountType::Fixed]);

    $updatedActivity = Activity::where('log_name', 'fee_discount')->where('event', 'updated')->first();
    expect($updatedActivity)->not->toBeNull()
        ->description->toBe('Updated fee discount "Sibling Discount".');

    expect($updatedActivity->properties->get('attributes'))
        ->toMatchArray([
            'discount_type' => 'fixed',
            'discount_type_label' => 'Fixed Amount (৳)',
        ]);

    $discount->delete();

    expect(Activity::where('log_name', 'fee_discount')->where('event', 'deleted')->first())
        ->not->toBeNull()
        ->description->toBe('Deleted fee discount "Sibling Discount".');
});

it('logs create, update, and delete of a student fee discount with student/fee-type/discount labels', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $discount = FeeDiscount::create(['name' => 'Sibling Discount', 'discount_type' => FeeDiscountType::Percent, 'discount_value' => 10]);

    $class = Classes::create(['name' => 'Class 8', 'order' => 8]);
    $user = User::factory()->create(['name' => 'Karim Hossain', 'user_type' => UserType::Student, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 5,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $studentDiscount = StudentFeeDiscount::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'discount_id' => $discount->id,
        'session_year' => now()->year,
        'approved_by' => $admin->id,
    ]);

    $createdActivity = Activity::where('log_name', 'student_fee_discount')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->description->toBe('Created fee discount "Sibling Discount" for "Class 8 - Karim Hossain (Roll: 5)" on "Tuition Fee".');

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'student_id' => $student->id,
            'student_id_label' => 'Class 8 - Karim Hossain (Roll: 5)',
            'fee_type_id' => $feeType->id,
            'fee_type_id_label' => 'Tuition Fee',
            'discount_id' => $discount->id,
            'discount_id_label' => 'Sibling Discount',
            'approved_by' => $admin->id,
            'approved_by_label' => $admin->name,
        ]);

    $studentDiscount->update(['remarks' => 'Verified by accountant']);

    expect(Activity::where('log_name', 'student_fee_discount')->where('event', 'updated')->first())
        ->not->toBeNull()
        ->description->toBe('Updated fee discount "Sibling Discount" for "Class 8 - Karim Hossain (Roll: 5)" on "Tuition Fee".');

    $studentDiscount->delete();

    expect(Activity::where('log_name', 'student_fee_discount')->where('event', 'deleted')->first())
        ->not->toBeNull()
        ->description->toBe('Deleted fee discount "Sibling Discount" for "Class 8 - Karim Hossain (Roll: 5)" on "Tuition Fee".');
});

it('omits the class segment when the student has no class assigned', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $feeType = FeeType::create(['name' => 'Tuition Fee', 'is_monthly' => true, 'is_active' => true]);
    $discount = FeeDiscount::create(['name' => 'Sibling Discount', 'discount_type' => FeeDiscountType::Percent, 'discount_value' => 10]);

    $user = User::factory()->create(['name' => 'Fatema Akter', 'user_type' => UserType::Student, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 9,
        'session_year' => now()->year,
        'gender' => Gender::Female,
        'status' => StudentStatus::Active,
    ]);

    StudentFeeDiscount::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'discount_id' => $discount->id,
        'session_year' => now()->year,
        'approved_by' => $admin->id,
    ]);

    $createdActivity = Activity::where('log_name', 'student_fee_discount')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->description->toBe('Created fee discount "Sibling Discount" for "Fatema Akter (Roll: 9)" on "Tuition Fee".');
});
