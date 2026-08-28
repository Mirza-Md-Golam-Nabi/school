<?php

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Filament\Resources\Marksheets\Pages\ManageClassMarksheets;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Marksheet;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeManageClassMarksheetsTestStudent(int $classId): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => random_int(1, 100000),
        'current_class_id' => $classId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function makeManageClassMarksheetsTestExam(int $classId): Exam
{
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $classId,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
    ]);
}

it('shows a danger notification instead of a raw 404 when no marksheet has been generated yet', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Marksheet Download Notify Class', 'order' => 1]);
    $exam = makeManageClassMarksheetsTestExam($class->id);

    Livewire::test(ManageClassMarksheets::class, ['classId' => $class->id])
        ->mountAction('downloadAllMarksheets')
        ->setActionData(['exam_id' => $exam->id])
        ->callMountedAction()
        ->assertNotified()
        ->assertNoRedirect();
});

it('redirects to the download route when at least one marksheet has been generated', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    test()->actingAs($admin);

    $class = Classes::create(['name' => 'Marksheet Download Redirect Class', 'order' => 1]);
    $exam = makeManageClassMarksheetsTestExam($class->id);
    $student = makeManageClassMarksheetsTestStudent($class->id);

    Marksheet::create([
        'student_id' => $student->id,
        'exam_id' => $exam->id,
        'is_generated' => true,
    ]);

    Livewire::test(ManageClassMarksheets::class, ['classId' => $class->id])
        ->mountAction('downloadAllMarksheets')
        ->setActionData(['exam_id' => $exam->id])
        ->callMountedAction()
        ->assertRedirect(route('marksheets.class.download', ['class' => $class->id, 'exam' => $exam->id]));
});
