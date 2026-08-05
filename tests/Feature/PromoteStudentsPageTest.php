<?php

use App\Enums\ExamConfigType;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\StudentProfiles\StudentProfileResource;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\ExamTypeConfig;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAdminUser(): User
{
    return grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin]));
}

function createEnrolledStudent(Classes $class, int $rollNo, int $sessionYear): StudentProfile
{
    return StudentProfile::create([
        'user_id' => User::factory()->create()->id,
        'roll_no' => $rollNo,
        'current_class_id' => $class->id,
        'session_year' => $sessionYear,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

test('new roll defaults to the main exam merit rank, not the old roll number', function () {
    $class = Classes::create(['name' => 'Class 5', 'order' => 1]);
    $student = createEnrolledStudent($class, rollNo: 9, sessionYear: 2026);

    $examType = ExamType::create(['name' => 'Final Exam', 'is_active' => true]);
    ExamTypeConfig::create(['exam_type_id' => $examType->id, 'type' => ExamConfigType::Main]);

    $exam = Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $class->id,
        'session_year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-10',
    ]);

    StudentMeritRanking::create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'class_rank' => 2,
    ]);

    $response = $this->actingAs(createAdminUser())->get(
        StudentProfileResource::getUrl('promote-students', ['classId' => $class->id])
    );

    $response->assertOk();
    $response->assertDontSee('Main Exam-এর merit ranking পাওয়া যায়নি');
});

test('new roll stays blank when the class has no main exam results', function () {
    $class = Classes::create(['name' => 'Class 5', 'order' => 1]);
    createEnrolledStudent($class, rollNo: 9, sessionYear: 2026);

    $response = $this->actingAs(createAdminUser())->get(
        StudentProfileResource::getUrl('promote-students', ['classId' => $class->id])
    );

    $response->assertOk();
    $response->assertSee('Main Exam-এর merit ranking পাওয়া যায়নি');
});
