<?php

use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Filament\Teacher\Pages\EnterStudentMarks;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TeacherSubject;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('logs exam marks entered by a teacher with the class named first in the description', function () {
    $class = Classes::create(['name' => 'Class 9', 'order' => 9, 'is_active' => true]);

    $teacherUser = User::factory()->create(['name' => 'Rafiq Sir', 'user_type' => UserType::Teacher, 'is_active' => true]);
    $teacher = TeacherProfile::create([
        'user_id' => $teacherUser->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);

    $subject = Subject::create(['name' => 'Chemistry', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);

    $examType = ExamType::create(['name' => 'Annual', 'is_active' => true]);
    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => 2026,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'is_published' => false,
    ]);

    TeacherSubject::create([
        'teacher_id' => $teacher->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'session_year' => $exam->session_year,
    ]);

    $studentUser = User::factory()->create(['name' => 'Nadia Islam', 'user_type' => UserType::Student, 'is_active' => true]);
    $student = StudentProfile::create([
        'user_id' => $studentUser->id,
        'roll_no' => 7,
        'current_class_id' => $class->id,
        'session_year' => 2026,
        'gender' => Gender::Female,
        'status' => StudentStatus::Active,
    ]);

    test()->actingAs($teacherUser);

    Filament::setCurrentPanel('teacher');

    $component = Livewire::test(EnterStudentMarks::class, [
        'examId' => $exam->id,
        'subjectId' => $subject->id,
        'classId' => $class->id,
    ])->instance();

    $component->marks[$student->id]['written_marks'] = 45;
    $component->marks[$student->id]['is_absent'] = false;
    $component->save();

    $activity = Activity::where('log_name', 'student_result')->where('event', 'created')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($teacherUser->id)
        ->and($activity->description)->toBe('Class 9 (2026) - Annual - Chemistry - Nadia Islam (Roll: 7)');
});
