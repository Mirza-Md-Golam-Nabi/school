<?php

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentProfiles\Pages\StudentsByClass;
use App\Filament\Resources\StudentProfiles\StudentProfileResource;
use App\Filament\Resources\StudentProfiles\Widgets\NewlyPromotedStudentsTableWidget;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createCohortSplitTestStudent(Classes $class, int $rollNo, int $sessionYear): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => $sessionYear,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it('shows only the not-yet-promoted cohort in the main table when the class holds two cohorts', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Cohort Split Class 2', 'order' => 2]);

    $awaitingPromotion = createCohortSplitTestStudent($class, rollNo: 3, sessionYear: 2026);
    $newlyPromotedIn = createCohortSplitTestStudent($class, rollNo: 1, sessionYear: 2027);

    test()->actingAs($admin);

    Livewire::test(StudentsByClass::class, ['classId' => $class->id])
        ->assertCanSeeTableRecords([$awaitingPromotion])
        ->assertCanNotSeeTableRecords([$newlyPromotedIn]);
});

it('shows the newly-promoted cohort in the footer widget table', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Cohort Split Class 2 Widget', 'order' => 2]);

    $awaitingPromotion = createCohortSplitTestStudent($class, rollNo: 3, sessionYear: 2026);
    $newlyPromotedIn = createCohortSplitTestStudent($class, rollNo: 1, sessionYear: 2027);

    test()->actingAs($admin);

    Livewire::test(NewlyPromotedStudentsTableWidget::class, [
        'classId' => $class->id,
        'awaitingSessionYear' => 2026,
    ])
        ->assertCanSeeTableRecords([$newlyPromotedIn])
        ->assertCanNotSeeTableRecords([$awaitingPromotion]);
});

it('shows the footer widget heading on the class page when a newly-promoted cohort exists', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Cohort Split Class 2 Heading', 'order' => 2]);

    createCohortSplitTestStudent($class, rollNo: 3, sessionYear: 2026);
    createCohortSplitTestStudent($class, rollNo: 1, sessionYear: 2027);

    $response = $this->actingAs($admin)->get(
        StudentProfileResource::getUrl('students-by-class', ['classId' => $class->id])
    );

    $response->assertOk();
    $response->assertSee('নতুন উত্তীর্ণ হওয়া Students');
});

it('hides the footer widget when the class holds only one cohort', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Cohort Split Class Single', 'order' => 3]);

    createCohortSplitTestStudent($class, rollNo: 1, sessionYear: 2026);

    $response = $this->actingAs($admin)->get(
        StudentProfileResource::getUrl('students-by-class', ['classId' => $class->id])
    );

    $response->assertOk();
    $response->assertDontSee('নতুন উত্তীর্ণ হওয়া Students');
});

it('hides both table headings once every student has been promoted out of the class', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Cohort Split Class Emptied', 'order' => 4]);
    $nextClass = Classes::create(['name' => 'Cohort Split Class Emptied Next', 'order' => 5]);

    $student = createCohortSplitTestStudent($class, rollNo: 1, sessionYear: 2026);
    $student->update(['current_class_id' => $nextClass->id]);

    $response = $this->actingAs($admin)->get(
        StudentProfileResource::getUrl('students-by-class', ['classId' => $class->id])
    );

    $response->assertOk();
    $response->assertDontSee('Promote বাকি আছে Students');
    $response->assertDontSee('নতুন উত্তীর্ণ হওয়া Students');
});

it('hides both headings when only the newly-promoted cohort remains after the old batch moves on', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Cohort Split Class 7', 'order' => 7]);
    $nextClass = Classes::create(['name' => 'Cohort Split Class 8', 'order' => 8]);

    $awaitingPromotion = createCohortSplitTestStudent($class, rollNo: 3, sessionYear: 2026);
    createCohortSplitTestStudent($class, rollNo: 1, sessionYear: 2027);

    // Old batch promoted out of Class 7 entirely — only the newly-arrived cohort remains.
    $awaitingPromotion->update(['current_class_id' => $nextClass->id]);

    $response = $this->actingAs($admin)->get(
        StudentProfileResource::getUrl('students-by-class', ['classId' => $class->id])
    );

    $response->assertOk();
    $response->assertDontSee('Promote বাকি আছে Students');
    $response->assertDontSee('নতুন উত্তীর্ণ হওয়া Students');
});
