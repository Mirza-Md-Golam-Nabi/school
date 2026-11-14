<?php

use App\Actions\Attendance\ResolveStudentAttendanceTotals;
use App\Actions\Attendance\SaveExamAttendanceSummariesAction;
use App\Actions\BuildStudentMarksDetail;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Pages\ClassAttendanceRanking;
use App\Filament\Pages\ExamAttendanceTotals;
use App\Filament\Student\Widgets\StudentAttendanceRankOverview;
use App\Filament\Teacher\Pages\ExamAttendanceTotals as TeacherExamAttendanceTotals;
use App\Filament\Widgets\StudentAttendanceRankingWidget;
use App\Models\Attendance;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Permission;
use App\Models\StudentAttendanceSummary;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function totalsStudent(Classes $class, int $rollNo): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function totalsExam(Classes $class, string $typeName, string $startDate): Exam
{
    return Exam::create([
        'exam_type_id' => ExamType::create(['name' => $typeName, 'is_active' => true])->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => $startDate,
        'end_date' => $startDate,
        'is_published' => true,
    ]);
}

/**
 * One present row per day from Jan 2 — inserted directly to avoid the date cast quirk under SQLite.
 */
function totalsDeviceDays(StudentProfile $student, Classes $class, int $days): void
{
    foreach (range(1, $days) as $offset) {
        Attendance::insert([
            'attendable_type' => StudentProfile::class,
            'attendable_id' => $student->id,
            'date' => now()->startOfYear()->addDays($offset)->toDateString(),
            'status' => 'present',
            'source' => 'manual',
            'class_id' => $class->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function totalsGrantMarkAttendance(User $user): User
{
    Permission::firstOrCreate(['name' => 'mark_attendance', 'guard_name' => 'web']);
    $user->givePermissionTo('mark_attendance');
    app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

beforeEach(function () {
    $this->class = Classes::create(['name' => 'Class Five', 'order' => 5, 'is_active' => true]);
    $this->exam = totalsExam($this->class, 'Half Yearly', now()->startOfYear()->addMonths(5)->toDateString());
});

it('saves totals, removes blank ones and rejects present days above working days', function () {
    $first = totalsStudent($this->class, 1);
    $second = totalsStudent($this->class, 2);
    $action = app(SaveExamAttendanceSummariesAction::class);

    expect($action->handle($this->exam, 90, [$first->id => 82, $second->id => 70], null))->toBe(2);

    expect(StudentAttendanceSummary::where('exam_id', $this->exam->id)->pluck('present_days', 'student_id')->all())
        ->toBe([$first->id => 82, $second->id => 70]);

    $action->handle($this->exam, 90, [$first->id => 85, $second->id => ''], null);

    expect(StudentAttendanceSummary::where('exam_id', $this->exam->id)->pluck('present_days', 'student_id')->all())
        ->toBe([$first->id => 85]);

    expect(fn () => $action->handle($this->exam, 90, [$first->id => 91], null))
        ->toThrow(ValidationException::class);

    expect(fn () => $action->handle($this->exam, null, [$first->id => 10], null))
        ->toThrow(ValidationException::class);
});

it('ignores students outside the exam class', function () {
    $otherClass = Classes::create(['name' => 'Class Six', 'order' => 6, 'is_active' => true]);
    $outsider = totalsStudent($otherClass, 1);

    app(SaveExamAttendanceSummariesAction::class)->handle($this->exam, 90, [$outsider->id => 50], null);

    expect(StudentAttendanceSummary::count())->toBe(0);
});

it('shows the written total on the marksheet instead of the daily count', function () {
    $student = totalsStudent($this->class, 1);
    $other = totalsStudent($this->class, 2);
    totalsDeviceDays($student, $this->class, 20);
    totalsDeviceDays($other, $this->class, 18);

    StudentAttendanceSummary::create([
        'exam_id' => $this->exam->id, 'student_id' => $student->id, 'class_id' => $this->class->id,
        'working_days' => 90, 'present_days' => 82,
    ]);

    $ranking = fn (StudentProfile $profile) => StudentMeritRanking::create([
        'exam_id' => $this->exam->id, 'student_id' => $profile->id, 'class_id' => $this->class->id,
        'total_marks' => 0, 'gpa' => 0, 'class_rank' => $profile->roll_no,
    ]);

    $builder = app(BuildStudentMarksDetail::class);

    ['summary' => $summary] = $builder->handle($ranking($student));
    expect($summary['working_days'])->toBe(90)->and($summary['present_days'])->toBe(82);

    // A classmate without a written total keeps the daily-attendance numbers.
    ['summary' => $otherSummary] = $builder->handle($ranking($other));
    expect($otherSummary['working_days'])->toBe(20)->and($otherSummary['present_days'])->toBe(18);
});

it('uses the latest exam total of the year in rankings and dashboards', function () {
    $student = totalsStudent($this->class, 1);
    $rival = totalsStudent($this->class, 2);
    totalsDeviceDays($student, $this->class, 10);
    totalsDeviceDays($rival, $this->class, 20);

    $finalExam = totalsExam($this->class, 'Annual', now()->startOfYear()->addMonths(10)->toDateString());

    StudentAttendanceSummary::create([
        'exam_id' => $this->exam->id, 'student_id' => $student->id, 'class_id' => $this->class->id,
        'working_days' => 90, 'present_days' => 80,
    ]);
    StudentAttendanceSummary::create([
        'exam_id' => $finalExam->id, 'student_id' => $student->id, 'class_id' => $this->class->id,
        'working_days' => 180, 'present_days' => 170,
    ]);

    $resolver = app(ResolveStudentAttendanceTotals::class);

    expect($resolver->presentCountsForYear(now()->year, $this->class->id)->all())
        ->toBe([$student->id => 170, $rival->id => 20])
        ->and($resolver->workingDaysForYear(now()->year, $this->class->id))->toBe(180);

    $classRanking = new ClassAttendanceRanking;
    $classRanking->classId = $this->class->id;
    expect($classRanking->getStudents()->first()['present_count'])->toBe(170);

    $widget = (new StudentAttendanceRankingWidget)->getViewData();
    expect($widget['workingDays'])->toBe(180)
        ->and($widget['topStudents'][0]['present_count'])->toBe(170);

    test()->actingAs($rival->user);
    $studentWidget = (new StudentAttendanceRankOverview)->getViewData();
    expect($studentWidget['workingDays'])->toBe(180)
        ->and($studentWidget['presentDays'])->toBe(20)
        ->and($studentWidget['rank'])->toBe(2);
});

it('lets an admin prefill from device counts and save totals', function () {
    $student = totalsStudent($this->class, 1);
    totalsDeviceDays($student, $this->class, 20);

    Filament::setCurrentPanel('admin');
    test()->actingAs(grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true])));

    Livewire::test(ExamAttendanceTotals::class, ['examId' => $this->exam->id])
        ->assertSet('classId', $this->class->id)
        ->assertSet('workingDays', 20)
        ->assertSet("presentDays.{$student->id}", 20)
        ->set('workingDays', 90)
        ->set("presentDays.{$student->id}", 95)
        ->call('save')
        ->assertHasErrors("presentDays.{$student->id}")
        ->set("presentDays.{$student->id}", 82)
        ->call('save')
        ->assertHasNoErrors()
        ->assertNotified()
        ->assertActionEnabled('resetToDeviceCount')
        ->callAction('resetToDeviceCount')
        ->assertActionDisabled('resetToDeviceCount')
        ->assertSet("presentDays.{$student->id}", 20);

    expect(StudentAttendanceSummary::count())->toBe(0);
});

it('lets only the class teacher write totals in the teacher panel', function () {
    $student = totalsStudent($this->class, 1);
    $classTeacher = TeacherProfile::factory()->create();
    $otherTeacher = TeacherProfile::factory()->create();
    $this->class->update(['class_teacher_id' => $classTeacher->id]);

    Filament::setCurrentPanel('teacher');

    test()->actingAs(totalsGrantMarkAttendance($otherTeacher->user));
    expect(TeacherExamAttendanceTotals::canAccess())->toBeFalse();

    test()->actingAs(totalsGrantMarkAttendance($classTeacher->user));
    expect(TeacherExamAttendanceTotals::canAccess())->toBeTrue();

    Livewire::test(TeacherExamAttendanceTotals::class, ['examId' => $this->exam->id])
        ->set('workingDays', 90)
        ->set("presentDays.{$student->id}", 75)
        ->call('save')
        ->assertHasNoErrors();

    expect(StudentAttendanceSummary::first())
        ->present_days->toBe(75)
        ->entered_by->toBe($classTeacher->user_id);

    // Another class's exam is off limits.
    $otherClass = Classes::create(['name' => 'Class Six', 'order' => 6, 'is_active' => true]);
    $otherExam = totalsExam($otherClass, 'Annual', now()->toDateString());

    Livewire::test(TeacherExamAttendanceTotals::class, ['examId' => $otherExam->id])
        ->assertForbidden();
});
