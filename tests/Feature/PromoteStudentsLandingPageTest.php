<?php

use App\Enums\ClassLevel;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Pages\BulkPromoteStudentsForClass;
use App\Filament\Pages\PromoteStudents;
use App\Filament\Pages\PromoteStudentsForClass;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makePromoteLandingTestStudent(Classes $class, int $rollNo, int $sessionYear, StudentStatus $status = StudentStatus::Active): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create()->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => $sessionYear,
        'gender' => Gender::Male,
        'status' => $status,
    ]);
}

it('defaults the selected year to last year', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    Livewire::test(PromoteStudents::class)
        ->assertSet('year', now()->year - 1);
});

it('offers only the current year and last year in the dropdown', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $currentYear = now()->year;

    expect(Livewire::test(PromoteStudents::class)->instance()->getYearOptions())
        ->toBe([
            $currentYear => (string) $currentYear,
            $currentYear - 1 => (string) ($currentYear - 1),
        ]);
});

it('counts active students per class for the selected year only', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 4', 'order' => 4, 'is_active' => true]);
    $lastYear = now()->year - 1;

    makePromoteLandingTestStudent($class, 1, $lastYear);
    makePromoteLandingTestStudent($class, 2, $lastYear);
    // A different year and a graduated student must not count toward the selected year's total.
    makePromoteLandingTestStudent($class, 3, $lastYear + 1);
    makePromoteLandingTestStudent($class, 4, $lastYear, StudentStatus::Graduated);

    $classes = Livewire::test(PromoteStudents::class, ['year' => $lastYear])
        ->instance()
        ->getClasses();

    expect($classes->firstWhere('id', $class->id)->active_students_count)->toBe(2);
});

it('links each class card to the promotion form for the selected year', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 4', 'order' => 4, 'is_active' => true]);
    $lastYear = now()->year - 1;
    makePromoteLandingTestStudent($class, 1, $lastYear);

    $this->get(PromoteStudents::getUrl(['year' => $lastYear]))
        ->assertOk()
        ->assertSee(
            PromoteStudentsForClass::getUrl(['classId' => $class->id, 'year' => $lastYear])
        );
});

it('keeps the Promote nav item active on both of its hidden sub-pages', function () {
    $patterns = PromoteStudents::getNavigationItemActiveRoutePattern();

    expect($patterns)->toBeArray()
        ->and($patterns)->toContain(PromoteStudents::getRouteName())
        ->and($patterns)->toContain(PromoteStudentsForClass::getRouteName())
        ->and($patterns)->toContain(BulkPromoteStudentsForClass::getRouteName());
});

it('labels college sessions and flags the ones still running', function () {
    $this->travelTo(Carbon::parse('2027-02-10'));
    $this->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    $college = Classes::create(['name' => 'Class 11', 'order' => 11, 'level' => ClassLevel::College, 'is_active' => true]);
    $school = Classes::create(['name' => 'Class 9', 'order' => 9, 'level' => ClassLevel::Secondary, 'is_active' => true]);
    makePromoteLandingTestStudent($college, 1, 2026);
    makePromoteLandingTestStudent($school, 1, 2026);

    Livewire::test(PromoteStudents::class)
        ->assertSet('year', 2026)
        ->assertSee('Students (2026-27)')
        ->assertSee('Students (2026)')
        ->assertSeeTextInOrder(['Class 9', 'Class 11', 'সেশন চলছে']);

    Livewire::test(PromoteStudentsForClass::class, ['classId' => $college->id, 'year' => 2026])
        ->assertSee('Session 2026-27 → 2027-28')
        ->assertSee('Jun 2027 পর্যন্ত');

    Livewire::test(BulkPromoteStudentsForClass::class, ['classId' => $college->id, 'year' => 2026])
        ->assertSee('Jun 2027 পর্যন্ত');

    Livewire::test(PromoteStudentsForClass::class, ['classId' => $school->id, 'year' => 2026])
        ->assertSee('Session 2026 → 2027')
        ->assertDontSee('পর্যন্ত');
});
