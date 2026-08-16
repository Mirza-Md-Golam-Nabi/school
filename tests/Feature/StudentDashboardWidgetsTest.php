<?php

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Student\Widgets\StudentFeeDueOverview;
use App\Filament\Student\Widgets\StudentLatestResultWidget;
use App\Filament\Student\Widgets\StudentUpcomingExamWidget;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\FeeType;
use App\Models\StudentFeeInvoice;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeDashboardStudent(Classes $class): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

it('only lists exams that have not started yet for the student\'s class', function () {
    $class = Classes::create(['name' => 'Class Eight', 'order' => 8]);
    $student = makeDashboardStudent($class);
    $examType = ExamType::create(['name' => 'Final']);

    Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => today()->addDays(5)->toDateString(),
        'end_date' => today()->addDays(7)->toDateString(),
        'is_published' => false,
    ]);

    Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->subDays(8)->toDateString(),
        'is_published' => true,
    ]);

    $this->actingAs($student->user);

    expect(StudentUpcomingExamWidget::canView())->toBeTrue()
        ->and((new StudentUpcomingExamWidget)->getViewData()['exams'])->toHaveCount(1);
});

it('shows the latest-result widget with zeroed values when there is no merit ranking yet', function () {
    $class = Classes::create(['name' => 'Class Nine', 'order' => 9]);
    $student = makeDashboardStudent($class);

    $this->actingAs($student->user);

    expect(StudentLatestResultWidget::canView())->toBeTrue();

    $data = (new StudentLatestResultWidget)->getViewData();

    expect($data['gpa'])->toBe(0)
        ->and($data['classRank'])->toBeNull();
});

it('hides the latest-result widget when the student has no current class', function () {
    $student = makeDashboardStudent(Classes::create(['name' => 'Class Nine Unassigned', 'order' => 9]));
    $student->update(['current_class_id' => null]);

    $this->actingAs($student->user);

    expect(StudentLatestResultWidget::canView())->toBeFalse();
});

it('shows gpa and class rank from the most recently held published exam', function () {
    $class = Classes::create(['name' => 'Class Ten', 'order' => 10]);
    $student = makeDashboardStudent($class);
    $examType = ExamType::create(['name' => 'Half Yearly']);

    $olderExam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => today()->subDays(60)->toDateString(),
        'end_date' => today()->subDays(58)->toDateString(),
        'is_published' => true,
    ]);

    $newerExam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->subDays(8)->toDateString(),
        'is_published' => true,
    ]);

    StudentMeritRanking::create([
        'exam_id' => $olderExam->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'total_marks' => 400,
        'gpa' => 3.5,
        'class_rank' => 5,
    ]);

    StudentMeritRanking::create([
        'exam_id' => $newerExam->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'total_marks' => 450,
        'gpa' => 4.5,
        'class_rank' => 1,
    ]);

    $this->actingAs($student->user);

    expect(StudentLatestResultWidget::canView())->toBeTrue();

    $data = (new StudentLatestResultWidget)->getViewData();

    expect($data['gpa'])->toBe(4.5)
        ->and($data['classRank'])->toBe(1)
        ->and($data['totalStudents'])->toBe(1);
});

it('resets the latest-result widget to zero after promotion until the new class has a published result', function () {
    $oldClass = Classes::create(['name' => 'Class Nine Old', 'order' => 9]);
    $newClass = Classes::create(['name' => 'Class Ten New', 'order' => 10]);
    $student = makeDashboardStudent($oldClass);
    $examType = ExamType::create(['name' => 'Final']);

    $oldExam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $oldClass->id,
        'session_year' => now()->year,
        'start_date' => today()->subDays(60)->toDateString(),
        'end_date' => today()->subDays(58)->toDateString(),
        'is_published' => true,
    ]);

    StudentMeritRanking::create([
        'exam_id' => $oldExam->id,
        'student_id' => $student->id,
        'class_id' => $oldClass->id,
        'total_marks' => 400,
        'gpa' => 3.5,
        'class_rank' => 1,
    ]);

    $this->actingAs($student->user);
    expect((new StudentLatestResultWidget)->getViewData()['gpa'])->toBe(3.5);

    // Promotion: new class + next session_year, no exam there yet.
    $student->update(['current_class_id' => $newClass->id, 'session_year' => now()->year + 1]);

    // Re-authenticate so Auth::user()->studentProfile isn't a stale cached relation.
    $this->actingAs($student->user->fresh());

    expect(StudentLatestResultWidget::canView())->toBeTrue();

    $data = (new StudentLatestResultWidget)->getViewData();

    expect($data['gpa'])->toBe(0)
        ->and($data['classRank'])->toBeNull();
});

it('shows a red-banner due message when the student has an unpaid invoice', function () {
    $class = Classes::create(['name' => 'Class Six', 'order' => 6]);
    $student = makeDashboardStudent($class);
    $feeType = FeeType::create(['name' => 'Tuition']);

    StudentFeeInvoice::create([
        'student_id' => $student->id,
        'fee_type_id' => $feeType->id,
        'year' => now()->year,
        'original_amount' => 500,
        'net_amount' => 500,
    ]);

    $this->actingAs($student->user);

    Livewire::test(StudentFeeDueOverview::class)
        ->assertSee('border-danger-400', false);
});

it('shows the neutral card style when the student has no due invoices', function () {
    $class = Classes::create(['name' => 'Class Five', 'order' => 5]);
    $student = makeDashboardStudent($class);

    $this->actingAs($student->user);

    Livewire::test(StudentFeeDueOverview::class)
        ->assertDontSee('border-danger-400', false);
});
