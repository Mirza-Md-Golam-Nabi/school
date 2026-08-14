<?php

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Pages\StudentAttendanceRanking;
use App\Filament\Widgets\StudentAttendanceRankingWidget;
use App\Models\Attendance;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createRankingTestStudent(Classes $class, int $rollNo): StudentProfile
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
function markRankingAttendance(StudentProfile $student, Classes $class, string $date, string $status): void
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

it('counts distinct working days and ranks the top 3 students by present days', function () {
    $class = Classes::create(['name' => 'Class 1', 'order' => 1]);
    $topStudent = createRankingTestStudent($class, 1);
    $midStudent = createRankingTestStudent($class, 2);
    $lowStudent = createRankingTestStudent($class, 3);

    $dates = collect(range(1, 5))->map(fn ($i) => now()->startOfYear()->addDays($i)->toDateString());

    foreach ($dates as $index => $date) {
        markRankingAttendance($topStudent, $class, $date, 'present');

        if ($index < 3) {
            markRankingAttendance($midStudent, $class, $date, 'present');
        }

        if ($index < 1) {
            markRankingAttendance($lowStudent, $class, $date, 'present');
        }
    }

    $data = (new StudentAttendanceRankingWidget)->getViewData();

    expect($data['workingDays'])->toBe(5)
        ->and($data['topStudents'])->toHaveCount(2)
        ->and($data['topStudents'][0]['name'])->toBe($topStudent->user->name)
        ->and($data['topStudents'][0]['roll_no'])->toBe(1)
        ->and($data['topStudents'][0]['present_count'])->toBe(5)
        ->and($data['topStudents'][1]['present_count'])->toBe(3)
        ->and($data['url'])->toBe(route('filament.admin.pages.student-attendance-ranking'));
});

it('returns the top N students school-wide with their class name', function () {
    $class = Classes::create(['name' => 'Class 2', 'order' => 2]);
    $student = createRankingTestStudent($class, 1);

    markRankingAttendance($student, $class, now()->startOfYear()->addDay()->toDateString(), 'present');
    markRankingAttendance($student, $class, now()->startOfYear()->addDays(2)->toDateString(), 'present');

    $page = new StudentAttendanceRanking;
    $top = $page->getTopStudents(5);

    expect($top)->toHaveCount(1)
        ->and($top[0]['name'])->toBe($student->user->name)
        ->and($top[0]['roll_no'])->toBe(1)
        ->and($top[0]['class_name'])->toBe('Class 2')
        ->and($top[0]['present_count'])->toBe(2);
});

it('ranks the top 3 students per class independently', function () {
    $classA = Classes::create(['name' => 'Class 3', 'order' => 3]);
    $classB = Classes::create(['name' => 'Class 4', 'order' => 4]);

    $studentA = createRankingTestStudent($classA, 1);
    $studentB = createRankingTestStudent($classB, 1);

    markRankingAttendance($studentA, $classA, now()->startOfYear()->addDay()->toDateString(), 'present');
    markRankingAttendance($studentB, $classB, now()->startOfYear()->addDay()->toDateString(), 'present');
    markRankingAttendance($studentB, $classB, now()->startOfYear()->addDays(2)->toDateString(), 'present');

    $page = new StudentAttendanceRanking;
    $classWise = $page->getClassWiseTopStudents();

    $classAEntry = $classWise->firstWhere('class.id', $classA->id);
    $classBEntry = $classWise->firstWhere('class.id', $classB->id);

    expect($classAEntry['top'])->toHaveCount(1)
        ->and($classAEntry['top'][0]['present_count'])->toBe(1)
        ->and($classAEntry['top'][0]['roll_no'])->toBe(1)
        ->and($classBEntry['top'])->toHaveCount(1)
        ->and($classBEntry['top'][0]['present_count'])->toBe(2);
});

it('renders the ranking widget on the dashboard with a link to the detail page', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertSee('Working Days', false);
    $response->assertSee('href="'.route('filament.admin.pages.student-attendance-ranking').'"', false);
});

it('renders the ranking detail page', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));

    $response = $this->actingAs($admin)->get('/admin/student-attendance-ranking');

    $response->assertOk();
    $response->assertSee('Top 5 Students');
    $response->assertSee('Class-wise Top Attendance');
});
