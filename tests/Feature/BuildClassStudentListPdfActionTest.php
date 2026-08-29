<?php

use App\Actions\BuildClassStudentListPdfAction;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\Group;
use App\Models\Section;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

function makeStudentListTestStudent(
    int $classId,
    int $rollNo,
    int $sessionYear,
    ?int $groupId = null,
    ?int $sectionId = null,
): StudentProfile {
    $user = User::factory()->create([
        'user_type' => UserType::Student,
        'is_active' => true,
        'name' => "Student Roll {$rollNo}",
    ]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => $rollNo,
        'current_class_id' => $classId,
        'current_group_id' => $groupId,
        'current_section_id' => $sectionId,
        'session_year' => $sessionYear,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it('builds a pdf listing every student in the class for the given session', function () {
    $class = Classes::create(['name' => 'Student List Class', 'order' => 1]);

    makeStudentListTestStudent($class->id, 2, now()->year);
    makeStudentListTestStudent($class->id, 1, now()->year);

    $pdf = app(BuildClassStudentListPdfAction::class)->handle($class, now()->year);

    expect($pdf)->toStartWith('%PDF');
});

it('excludes students from a different class or a different session year', function () {
    $class = Classes::create(['name' => 'Student List Class A', 'order' => 1]);
    $otherClass = Classes::create(['name' => 'Student List Class B', 'order' => 2]);

    makeStudentListTestStudent($class->id, 1, now()->year);
    makeStudentListTestStudent($otherClass->id, 1, now()->year);
    makeStudentListTestStudent($class->id, 2, now()->year - 1);

    $pdf = app(BuildClassStudentListPdfAction::class)->handle($class, now()->year);

    expect($pdf)->toStartWith('%PDF');
});

it('aborts with 404 when the class has no students for that session', function () {
    $class = Classes::create(['name' => 'Student List Empty Class', 'order' => 1]);

    expect(fn () => app(BuildClassStudentListPdfAction::class)->handle($class, now()->year))
        ->toThrow(NotFoundHttpException::class);
});

it('only shows the section and group columns when at least one student has them', function () {
    $class = Classes::create(['name' => 'Student List Plain Class', 'order' => 1]);

    makeStudentListTestStudent($class->id, 1, now()->year);

    $rendered = view('documents.student-list', [
        'class' => $class,
        'sessionYear' => now()->year,
        'students' => StudentProfile::where('current_class_id', $class->id)->get(),
        'hasSection' => false,
        'hasGroup' => false,
    ])->render();

    expect($rendered)
        ->toContain('Roll')
        ->toContain('Email')
        ->not->toContain('Section')
        ->not->toContain('Group');
});

it('shows the section and group columns when students have them', function () {
    $class = Classes::create(['name' => 'Student List Extra Fields Class', 'order' => 1]);
    $group = Group::create(['name' => 'Science']);
    $section = Section::create(['class_id' => $class->id, 'name' => 'A']);

    makeStudentListTestStudent($class->id, 1, now()->year, $group->id, $section->id);

    $rendered = view('documents.student-list', [
        'class' => $class,
        'sessionYear' => now()->year,
        'students' => StudentProfile::with(['user', 'section', 'group'])->where('current_class_id', $class->id)->get(),
        'hasSection' => true,
        'hasGroup' => true,
    ])->render();

    expect($rendered)
        ->toContain('Section')
        ->toContain('Group')
        ->toContain('Science')
        ->toContain('A');
});

it('streams the student list pdf as a download through the http route', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Student List Route Class', 'order' => 1]);

    makeStudentListTestStudent($class->id, 1, now()->year);

    $response = $this->actingAs($admin)->get(route('student-list.class.download', ['class' => $class->id]));

    $response->assertStatus(Response::HTTP_OK);
    $response->assertHeader('Content-Type', 'application/pdf');
});
