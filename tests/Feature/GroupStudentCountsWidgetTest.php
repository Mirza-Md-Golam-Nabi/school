<?php

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentProfiles\Pages\StudentsByClass;
use App\Filament\Resources\StudentProfiles\Widgets\GroupStudentCountsWidget;
use App\Models\Classes;
use App\Models\Group;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeGroupWidgetTestStudent(Classes $class, int $rollNo, ?int $groupId): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student])->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'current_group_id' => $groupId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it('counts active current-session students per group', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $class = Classes::create(['name' => 'Class 9', 'order' => 9, 'is_active' => true, 'has_group' => true]);
    $science = Group::create(['name' => 'Science']);
    $commerce = Group::create(['name' => 'Commerce']);
    $humanities = Group::create(['name' => 'Humanities']);

    makeGroupWidgetTestStudent($class, 1, $science->id);
    makeGroupWidgetTestStudent($class, 2, $science->id);
    makeGroupWidgetTestStudent($class, 3, $commerce->id);
    makeGroupWidgetTestStudent($class, 4, $humanities->id);

    // Not counted: different class, different session year, and inactive status.
    $otherClass = Classes::create(['name' => 'Class 10', 'order' => 10, 'is_active' => true, 'has_group' => true]);
    makeGroupWidgetTestStudent($otherClass, 5, $science->id);

    $lastYearStudent = makeGroupWidgetTestStudent($class, 6, $science->id);
    $lastYearStudent->update(['session_year' => now()->year - 1]);

    $inactiveStudent = makeGroupWidgetTestStudent($class, 7, $science->id);
    $inactiveStudent->update(['status' => StudentStatus::Graduated]);

    Livewire::test(GroupStudentCountsWidget::class, ['classId' => $class->id])
        ->assertSee('Science')
        ->assertSee('Commerce')
        ->assertSee('Humanities')
        ->assertSee('2') // Science
        ->assertSee('1'); // Commerce and Humanities
});

it('shows the group widget on the class student list only when the class has groups', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $this->actingAs($admin);

    $groupedClass = Classes::create(['name' => 'Class 9', 'order' => 9, 'is_active' => true, 'has_group' => true]);
    $ungroupedClass = Classes::create(['name' => 'Class 6', 'order' => 6, 'is_active' => true, 'has_group' => false]);

    Livewire::test(StudentsByClass::class, ['classId' => $groupedClass->id])
        ->assertSee('Science')
        ->assertSee('Commerce')
        ->assertSee('Humanities');

    Livewire::test(StudentsByClass::class, ['classId' => $ungroupedClass->id])
        ->assertDontSee('Science')
        ->assertDontSee('Commerce')
        ->assertDontSee('Humanities');
});
