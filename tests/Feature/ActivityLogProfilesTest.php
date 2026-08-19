<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\Group;
use App\Models\SchoolAccount;
use App\Models\Section;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('logs create, update, and delete of a student profile with class/section/group labels', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Class 8', 'order' => 8]);
    $section = Section::create(['class_id' => $class->id, 'name' => 'Section A']);
    $group = Group::create(['name' => 'Science', 'is_active' => true]);
    $user = User::factory()->create(['name' => 'Karim Hossain', 'user_type' => UserType::Student, 'is_active' => true]);

    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 5,
        'current_class_id' => $class->id,
        'current_section_id' => $section->id,
        'current_group_id' => $group->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    $createdActivity = Activity::where('log_name', 'student_profile')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->description->toBe('Created student profile "Karim Hossain (Roll: 5) - Class 8".');

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'user_id' => $user->id,
            'user_id_label' => 'Karim Hossain',
            'current_class_id' => $class->id,
            'current_class_id_label' => 'Class 8',
            'current_section_id' => $section->id,
            'current_section_id_label' => 'Section A',
            'current_group_id' => $group->id,
            'current_group_id_label' => 'Science',
        ]);

    $student->update(['roll_no' => 6]);

    $updatedActivity = Activity::where('log_name', 'student_profile')->where('event', 'updated')->first();
    expect($updatedActivity)->not->toBeNull()
        ->description->toBe('Updated student profile "Karim Hossain (Roll: 6) - Class 8".');

    $student->delete();

    expect(Activity::where('log_name', 'student_profile')->where('event', 'deleted')->first())
        ->not->toBeNull()
        ->description->toBe('Deleted student profile "Karim Hossain (Roll: 6) - Class 8".');
});

it('omits the class segment in the description when a student has no class assigned', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $user = User::factory()->create(['name' => 'Fatema Akter', 'user_type' => UserType::Student, 'is_active' => true]);

    StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 9,
        'session_year' => now()->year,
        'gender' => Gender::Female,
        'status' => StudentStatus::Active,
    ]);

    $createdActivity = Activity::where('log_name', 'student_profile')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->description->toBe('Created student profile "Fatema Akter (Roll: 9)".');
});

it('logs create, update, and delete of a teacher profile with the school account label', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $account = SchoolAccount::create(['name' => 'Main Account', 'current_balance' => 0]);
    $user = User::factory()->create(['name' => 'Rahim Sir', 'user_type' => UserType::Teacher, 'is_active' => true]);

    $teacher = TeacherProfile::create([
        'user_id' => $user->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
        'default_school_account_id' => $account->id,
    ]);

    $createdActivity = Activity::where('log_name', 'teacher_profile')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->description->toBe('Created teacher profile "Rahim Sir".');

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'user_id' => $user->id,
            'user_id_label' => 'Rahim Sir',
            'default_school_account_id' => $account->id,
            'default_school_account_id_label' => 'Main Account',
        ]);

    $teacher->update(['designation' => 'Senior Teacher']);

    $updatedActivity = Activity::where('log_name', 'teacher_profile')->where('event', 'updated')->first();
    expect($updatedActivity)->not->toBeNull()
        ->description->toBe('Updated teacher profile "Rahim Sir".');

    $teacher->delete();

    expect(Activity::where('log_name', 'teacher_profile')->where('event', 'deleted')->first())
        ->not->toBeNull()
        ->description->toBe('Deleted teacher profile "Rahim Sir".');
});

it('logs create, update, and delete of a staff profile with the school account label', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $account = SchoolAccount::create(['name' => 'Main Account', 'current_balance' => 0]);
    $user = User::factory()->create(['name' => 'Salma Begum', 'user_type' => UserType::Staff, 'is_active' => true]);

    $staff = StaffProfile::create([
        'user_id' => $user->id,
        'gender' => Gender::Female,
        'status' => EmploymentStatus::Active,
        'default_school_account_id' => $account->id,
    ]);

    $createdActivity = Activity::where('log_name', 'staff_profile')->where('event', 'created')->first();

    expect($createdActivity)->not->toBeNull()
        ->description->toBe('Created staff profile "Salma Begum".');

    expect($createdActivity->properties->get('attributes'))
        ->toMatchArray([
            'user_id' => $user->id,
            'user_id_label' => 'Salma Begum',
            'default_school_account_id' => $account->id,
            'default_school_account_id_label' => 'Main Account',
        ]);

    $staff->update(['designation' => 'Accountant']);

    $updatedActivity = Activity::where('log_name', 'staff_profile')->where('event', 'updated')->first();
    expect($updatedActivity)->not->toBeNull()
        ->description->toBe('Updated staff profile "Salma Begum".');

    $staff->delete();

    expect(Activity::where('log_name', 'staff_profile')->where('event', 'deleted')->first())
        ->not->toBeNull()
        ->description->toBe('Deleted staff profile "Salma Begum".');
});
