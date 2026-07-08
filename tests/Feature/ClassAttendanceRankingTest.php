<?php

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Pages\ClassAttendanceRanking;
use App\Filament\Pages\StudentAttendanceRanking;
use App\Models\Attendance;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createClassRankingTestStudent(Classes $class, int $rollNo): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create()->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

/**
 * Bypasses Eloquent's `date` cast, which appends a spurious time
 * component under SQLite and breaks exact-string date lookups.
 */
function markClassRankingAttendance(StudentProfile $student, Classes $class, string $date, string $status): void
{
    Attendance::insert([
        'attendable_type' => StudentProfile::class,
        'attendable_id' => $student->id,
        'date' => $date,
        'status' => $status,
        'source' => 'manual',
        'class_id' => $class->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('lists every student in the class, including those with zero attendance, ranked by present days', function () {
    $class = Classes::create(['name' => 'Class 5', 'order' => 5]);

    $topStudent = createClassRankingTestStudent($class, 1);
    $zeroStudent = createClassRankingTestStudent($class, 2);

    markClassRankingAttendance($topStudent, $class, now()->startOfYear()->addDay()->toDateString(), 'present');
    markClassRankingAttendance($topStudent, $class, now()->startOfYear()->addDays(2)->toDateString(), 'present');

    $page = new ClassAttendanceRanking;
    $page->classId = $class->id;

    $students = $page->getStudents();

    expect($students)->toHaveCount(2)
        ->and($students[0]['name'])->toBe($topStudent->user->name)
        ->and($students[0]['roll_no'])->toBe(1)
        ->and($students[0]['present_count'])->toBe(2)
        ->and($students[1]['name'])->toBe($zeroStudent->user->name)
        ->and($students[1]['present_count'])->toBe(0);
});

it('only counts attendance recorded for that specific class', function () {
    $classA = Classes::create(['name' => 'Class 6', 'order' => 6]);
    $classB = Classes::create(['name' => 'Class 7', 'order' => 7]);

    $student = createClassRankingTestStudent($classA, 1);
    markClassRankingAttendance($student, $classA, now()->startOfYear()->addDay()->toDateString(), 'present');

    $page = new ClassAttendanceRanking;
    $page->classId = $classB->id;

    expect($page->getStudents())->toBeEmpty();
});

it('renders the class ranking page for a given classId', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $class = Classes::create(['name' => 'Class 8', 'order' => 8]);
    $student = createClassRankingTestStudent($class, 1);

    markClassRankingAttendance($student, $class, now()->startOfYear()->addDay()->toDateString(), 'present');

    $response = $this->actingAs($admin)->get('/admin/class-attendance-ranking?classId='.$class->id);

    $response->assertOk();
    $response->assertSee('Class 8');
    $response->assertSee($student->user->name);
});

it('links each class card on the ranking page to its class attendance ranking page', function () {
    $admin = User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]);
    $class = Classes::create(['name' => 'Class 9', 'order' => 9]);

    $response = $this->actingAs($admin)->get(StudentAttendanceRanking::getUrl());

    $response->assertOk();
    $response->assertSee('href="'.ClassAttendanceRanking::getUrl(['classId' => $class->id]).'"', false);
});
