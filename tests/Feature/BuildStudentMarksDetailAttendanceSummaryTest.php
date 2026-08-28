<?php

use App\Actions\BuildStudentMarksDetail;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Models\Attendance;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reports working days and present days for the student in the exam class and session year', function () {
    $class = Classes::create(['name' => 'Class Five', 'order' => 5, 'is_active' => true]);
    $examType = ExamType::create(['name' => 'Annual', 'is_active' => true]);
    $sessionYear = now()->year;

    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => $sessionYear,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => true,
    ]);

    $subject = Subject::create(['name' => 'Math', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    ExamSubjectConfig::create([
        'exam_id' => $exam->id,
        'subject_id' => $subject->id,
        'written_total' => 100,
        'total_marks' => 100,
        'pass_mark' => 33,
    ]);

    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => 7,
        'current_class_id' => $class->id,
        'session_year' => $sessionYear,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);

    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);

    $ranking = StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'total_marks' => 80,
        'gpa' => 5.0,
        'class_rank' => 1,
    ]);

    // Three distinct dates the class took attendance — the student was present on two, absent on one.
    Attendance::create(['attendable_type' => StudentProfile::class, 'attendable_id' => $student->id, 'class_id' => $class->id, 'date' => "{$sessionYear}-01-10", 'status' => AttendanceStatus::Present, 'source' => AttendanceSource::Manual]);
    Attendance::create(['attendable_type' => StudentProfile::class, 'attendable_id' => $student->id, 'class_id' => $class->id, 'date' => "{$sessionYear}-01-11", 'status' => AttendanceStatus::Present, 'source' => AttendanceSource::Manual]);
    Attendance::create(['attendable_type' => StudentProfile::class, 'attendable_id' => $student->id, 'class_id' => $class->id, 'date' => "{$sessionYear}-01-12", 'status' => AttendanceStatus::Absent, 'source' => AttendanceSource::Manual]);

    // A different class's attendance on the same day must not count toward this class's working days.
    $otherClass = Classes::create(['name' => 'Class Six', 'order' => 6, 'is_active' => true]);
    Attendance::create(['attendable_type' => StudentProfile::class, 'attendable_id' => $student->id, 'class_id' => $otherClass->id, 'date' => "{$sessionYear}-01-13", 'status' => AttendanceStatus::Present, 'source' => AttendanceSource::Manual]);

    // Attendance from a different session year must not count either.
    Attendance::create(['attendable_type' => StudentProfile::class, 'attendable_id' => $student->id, 'class_id' => $class->id, 'date' => ($sessionYear - 1).'-01-10', 'status' => AttendanceStatus::Present, 'source' => AttendanceSource::Manual]);

    ['summary' => $summary] = app(BuildStudentMarksDetail::class)->handle($ranking);

    expect($summary['working_days'])->toBe(3)
        ->and($summary['present_days'])->toBe(2);
});
