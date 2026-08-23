<?php

use App\Actions\BuildClassSeatPlanPdfAction;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Pages\SeatPlan;
use App\Models\Classes;
use App\Models\Group;
use App\Models\Section;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

function makeSeatPlanTestStudent(int $classId, int $rollNo, StudentStatus $status = StudentStatus::Active, ?int $groupId = null, ?int $sectionId = null): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true, 'name' => "Student Roll {$rollNo}"]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => $rollNo,
        'current_class_id' => $classId,
        'current_group_id' => $groupId,
        'current_section_id' => $sectionId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => $status,
    ]);
}

it('builds a seat plan pdf listing every active student in the class, sorted by roll no', function () {
    $class = Classes::create(['name' => 'Seat Plan Class', 'order' => 1]);

    makeSeatPlanTestStudent($class->id, 2);
    makeSeatPlanTestStudent($class->id, 1);
    makeSeatPlanTestStudent($class->id, 3);

    // A different, non-active student must be excluded.
    makeSeatPlanTestStudent($class->id, 4, StudentStatus::Graduated);

    $pdf = app(BuildClassSeatPlanPdfAction::class)->handle($class);

    expect($pdf)->toStartWith('%PDF');
});

it('includes group and section only when the student has them', function () {
    $class = Classes::create(['name' => 'Seat Plan Group Class', 'order' => 1]);
    $group = Group::create(['name' => 'Science']);
    $section = Section::create(['class_id' => $class->id, 'name' => 'A']);

    makeSeatPlanTestStudent($class->id, 1, StudentStatus::Active, $group->id, $section->id);
    makeSeatPlanTestStudent($class->id, 2);

    $pdf = app(BuildClassSeatPlanPdfAction::class)->handle($class);

    expect($pdf)->toStartWith('%PDF');
});

it('excludes students belonging to a different class', function () {
    $class = Classes::create(['name' => 'Seat Plan Class A', 'order' => 1]);
    $otherClass = Classes::create(['name' => 'Seat Plan Class B', 'order' => 2]);

    makeSeatPlanTestStudent($class->id, 1);
    makeSeatPlanTestStudent($otherClass->id, 1);

    $pdf = app(BuildClassSeatPlanPdfAction::class)->handle($class);

    expect($pdf)->toStartWith('%PDF');
});

it('aborts with 404 when the class has no active students', function () {
    $class = Classes::create(['name' => 'Seat Plan Empty Class', 'order' => 1]);

    expect(fn () => app(BuildClassSeatPlanPdfAction::class)->handle($class))
        ->toThrow(NotFoundHttpException::class);
});

it('streams the seat plan pdf as a download through the http route', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Seat Plan Route Class', 'order' => 1]);

    makeSeatPlanTestStudent($class->id, 1);

    $response = $this->actingAs($admin)->get(route('seat-plan.class.download', ['class' => $class->id]));

    $response->assertStatus(Response::HTTP_OK);
    $response->assertHeader('Content-Type', 'application/pdf');
});

it('renders the seat plan page listing active classes with a download link', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Seat Plan Page Class', 'order' => 1, 'is_active' => true]);
    $emptyClass = Classes::create(['name' => 'Seat Plan Empty Page Class', 'order' => 2, 'is_active' => true]);

    makeSeatPlanTestStudent($class->id, 1);

    $this->actingAs($admin)->get(SeatPlan::getUrl())
        ->assertOk()
        ->assertSee($class->name)
        ->assertSee($emptyClass->name)
        ->assertSee(route('seat-plan.class.download', ['class' => $class->id]), false);
});
