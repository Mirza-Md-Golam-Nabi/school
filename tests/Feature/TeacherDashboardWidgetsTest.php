<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\PaymentMethod;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Teacher\Widgets\ClassAttendanceTodayWidget;
use App\Filament\Teacher\Widgets\TeacherFeeDuesWidget;
use App\Filament\Teacher\Widgets\TeacherMarksPendingWidget;
use App\Filament\Teacher\Widgets\TeacherUpcomingExamsWidget;
use App\Models\Attendance;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\FeePayment;
use App\Models\FeeType;
use App\Models\StudentFeeInvoice;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TeacherSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeDashboardTeacher(): TeacherProfile
{
    return TeacherProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
}

function makeDashboardClass(?TeacherProfile $classTeacher = null): Classes
{
    return Classes::create([
        'name' => 'Class '.str()->random(4),
        'order' => 1,
        'is_active' => true,
        'class_teacher_id' => $classTeacher?->id,
    ]);
}

function addDashboardStudent(Classes $class, int $rollNo): StudentProfile
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
function markStudentPresent(StudentProfile $student, Classes $class, string $date): void
{
    Attendance::insert([
        'attendable_type' => StudentProfile::class,
        'attendable_id' => $student->id,
        'class_id' => $class->id,
        'subject_id' => null,
        'date' => $date,
        'status' => 'present',
        'source' => 'manual',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('hides the class-attendance widget for a teacher who is not a class teacher', function () {
    $teacher = makeDashboardTeacher();
    $this->actingAs($teacher->user);

    expect(ClassAttendanceTodayWidget::canView())->toBeFalse();
});

it('shows the class-attendance widget for an assigned class teacher', function () {
    $teacher = makeDashboardTeacher();
    makeDashboardClass($teacher);
    $this->actingAs($teacher->user);

    expect(ClassAttendanceTodayWidget::canView())->toBeTrue();
});

it('marks a class as pending when today\'s attendance has not been taken', function () {
    $teacher = makeDashboardTeacher();
    $class = makeDashboardClass($teacher);
    addDashboardStudent($class, 1);

    $this->actingAs($teacher->user);

    $data = (new ClassAttendanceTodayWidget)->getViewData();
    $viewClass = $data['classes']->firstWhere('id', $class->id);

    expect($viewClass->is_marked)->toBeFalse()
        ->and($viewClass->student_profiles_count)->toBe(1);
});

it('marks a class as done and counts present students once attendance is taken', function () {
    $teacher = makeDashboardTeacher();
    $class = makeDashboardClass($teacher);
    $student = addDashboardStudent($class, 1);
    markStudentPresent($student, $class, today()->toDateString());

    $this->actingAs($teacher->user);

    $data = (new ClassAttendanceTodayWidget)->getViewData();
    $viewClass = $data['classes']->firstWhere('id', $class->id);

    expect($viewClass->is_marked)->toBeTrue()
        ->and($viewClass->present_today)->toBe(1)
        ->and($viewClass->absent_today)->toBe(0);
});

it('renders the teacher dashboard with the quick-actions and class-attendance widgets', function () {
    $teacher = makeDashboardTeacher();
    makeDashboardClass($teacher);

    $response = $this->actingAs($teacher->user)->get(route('filament.teacher.pages.dashboard'));

    $response->assertOk()
        ->assertSee('Quick Actions')
        ->assertSee("Today's Attendance — My Classes");

    $this->actingAs($teacher->user)
        ->withSession(['locale' => 'bn'])
        ->get(route('filament.teacher.pages.dashboard'))
        ->assertOk()
        ->assertSee('দ্রুত কাজ')
        ->assertSee('আজকের উপস্থিতি — আমার ক্লাস');
});

it('sums outstanding fee dues for the class teacher\'s students', function () {
    $teacher = makeDashboardTeacher();
    $class = makeDashboardClass($teacher);
    $student = addDashboardStudent($class, 1);
    $feeType = FeeType::create(['name' => 'Tuition']);

    $invoice = StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'year' => now()->year,
        'original_amount' => 1000,
        'net_amount' => 1000,
    ]);

    FeePayment::create([
        'receipt_no' => 'RCP-'.str()->random(6),
        'student_id' => $student->id,
        'invoice_id' => $invoice->id,
        'amount_paid' => 300,
        'payment_method' => PaymentMethod::Cash,
        'payment_date' => today()->toDateString(),
    ]);

    $this->actingAs($teacher->user);

    $data = (new TeacherFeeDuesWidget)->getViewData();

    expect($data['totalDue'])->toBe(700.0)
        ->and($data['studentsWithDues'])->toBe(1);
});

it('lists a subject exam as pending until the assigned teacher enters marks', function () {
    $teacher = makeDashboardTeacher();
    $class = makeDashboardClass();
    $subject = Subject::create(['name' => 'Mathematics']);
    $examType = ExamType::create(['name' => 'Final']);

    TeacherSubject::create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
    ]);

    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDays(3)->toDateString(),
        'is_published' => false,
    ]);

    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);

    $this->actingAs($teacher->user);

    expect(TeacherMarksPendingWidget::canView())->toBeTrue()
        ->and((new TeacherMarksPendingWidget)->getViewData()['pending'])->toHaveCount(1);

    $student = addDashboardStudent($class, 1);
    StudentResult::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'written_marks' => 60,
        'is_absent' => false,
    ]);

    expect((new TeacherMarksPendingWidget)->getViewData()['pending'])->toHaveCount(0);
});

it('shows only future exams in the taught classes upcoming widget', function () {
    $teacher = makeDashboardTeacher();
    $class = makeDashboardClass();
    $subject = Subject::create(['name' => 'Science']);
    $examType = ExamType::create(['name' => 'Half Yearly']);

    TeacherSubject::create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
    ]);

    Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => today()->addDays(5)->toDateString(),
        'end_date' => today()->addDays(7)->toDateString(),
        'is_published' => true,
    ]);

    Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->subDays(8)->toDateString(),
        'is_published' => true,
    ]);

    $this->actingAs($teacher->user);

    expect(TeacherUpcomingExamsWidget::canView())->toBeTrue()
        ->and((new TeacherUpcomingExamsWidget)->getViewData()['exams'])->toHaveCount(1);
});
