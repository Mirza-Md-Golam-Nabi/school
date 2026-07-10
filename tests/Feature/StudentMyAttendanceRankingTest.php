<?php

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Student\Pages\MyAttendanceRanking;
use App\Filament\Student\Widgets\StudentAttendanceRankOverview;
use App\Models\Attendance;
use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createMyRankingTestStudent(Classes $class, int $rollNo, string $name): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['name' => $name, 'user_type' => UserType::Student, 'is_active' => true])->id,
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
function markMyRankingAttendance(StudentProfile $student, Classes $class, string $date, string $status): void
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

it('ranks every student in the class by present days, highest first', function () {
    $class = Classes::create(['name' => 'Class Seven', 'order' => 7]);

    $me = createMyRankingTestStudent($class, 2, 'Me Student');
    $topStudent = createMyRankingTestStudent($class, 1, 'Top Student');
    $lastStudent = createMyRankingTestStudent($class, 3, 'Last Student');

    markMyRankingAttendance($topStudent, $class, now()->startOfYear()->addDay()->toDateString(), 'present');
    markMyRankingAttendance($topStudent, $class, now()->startOfYear()->addDays(2)->toDateString(), 'present');
    markMyRankingAttendance($me, $class, now()->startOfYear()->addDay()->toDateString(), 'present');

    $this->actingAs($me->user);

    $page = new MyAttendanceRanking;
    $students = $page->getStudents();

    expect($students)->toHaveCount(3)
        ->and($students[0]['name'])->toBe('Top Student')
        ->and($students[0]['present_count'])->toBe(2)
        ->and($students[1]['name'])->toBe('Me Student')
        ->and($students[1]['present_count'])->toBe(1)
        ->and($students[2]['name'])->toBe('Last Student')
        ->and($students[2]['present_count'])->toBe(0)
        ->and($page->getMyStudentId())->toBe($me->id);
});

it('renders the ranking page with every classmate and highlights my own row', function () {
    $class = Classes::create(['name' => 'Class Six', 'order' => 6]);

    $me = createMyRankingTestStudent($class, 5, 'Rahim Student');
    $classmate = createMyRankingTestStudent($class, 6, 'Karim Student');

    markMyRankingAttendance($classmate, $class, now()->startOfYear()->addDay()->toDateString(), 'present');

    $response = $this->actingAs($me->user)->get(MyAttendanceRanking::getUrl(panel: 'student'));

    $response->assertOk()
        ->assertSee('Rahim Student')
        ->assertSee('Karim Student')
        ->assertSee('(You)');
});

it('links the widget to the attendance ranking page', function () {
    $class = Classes::create(['name' => 'Class Five', 'order' => 5]);
    $me = createMyRankingTestStudent($class, 1, 'Solo Student');

    $this->actingAs($me->user);

    $data = (new StudentAttendanceRankOverview)->getViewData();

    expect($data['url'])->toBe(MyAttendanceRanking::getUrl(panel: 'student'));
});
