<?php

use App\Actions\BuildCombinedClassAdmitCardsPdfAction;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\AdmitCard;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

function makeCombinedAdmitCardTestStudent(int $classId, int $rollNo): StudentProfile
{
    $user = User::factory()->create(['user_type' => UserType::Student, 'is_active' => true]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'roll_no' => $rollNo,
        'current_class_id' => $classId,
        'session_year' => now()->year,
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
    ]);
}

function makeCombinedAdmitCardTestExam(int $classId): Exam
{
    $examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    return Exam::create([
        'exam_type_id' => $examType->id,
        'class_id' => $classId,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'is_published' => true,
    ]);
}

it('combines only already-generated admit cards for the given class and exam, ten per page', function () {
    $class = Classes::create(['name' => 'Combined Admit Class', 'order' => 1]);
    $exam = makeCombinedAdmitCardTestExam($class->id);

    foreach (range(1, 11) as $rollNo) {
        $student = makeCombinedAdmitCardTestStudent($class->id, $rollNo);
        AdmitCard::create([
            'student_id' => $student->id,
            'exam_id' => $exam->id,
            'is_generated' => true,
            'page_size' => 'A4',
        ]);
    }

    // Has an admit card row but it was never generated — must be excluded.
    $notGenerated = makeCombinedAdmitCardTestStudent($class->id, 12);
    AdmitCard::create([
        'student_id' => $notGenerated->id,
        'exam_id' => $exam->id,
        'is_generated' => false,
        'page_size' => 'A4',
    ]);

    $pdf = app(BuildCombinedClassAdmitCardsPdfAction::class)->handle($class, $exam);

    expect($pdf)->toStartWith('%PDF');

    // 11 generated cards, 10 per page => 2 pages.
    $pageCount = preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
    expect($pageCount)->toBe(2);
});

it('excludes generated admit cards belonging to a different class', function () {
    $class = Classes::create(['name' => 'Combined Admit Class A', 'order' => 1]);
    $otherClass = Classes::create(['name' => 'Combined Admit Class B', 'order' => 2]);
    $exam = makeCombinedAdmitCardTestExam($class->id);

    $inClass = makeCombinedAdmitCardTestStudent($class->id, 1);
    AdmitCard::create([
        'student_id' => $inClass->id,
        'exam_id' => $exam->id,
        'is_generated' => true,
        'page_size' => 'A4',
    ]);

    $otherStudent = makeCombinedAdmitCardTestStudent($otherClass->id, 1);
    AdmitCard::create([
        'student_id' => $otherStudent->id,
        'exam_id' => $exam->id,
        'is_generated' => true,
        'page_size' => 'A4',
    ]);

    $pdf = app(BuildCombinedClassAdmitCardsPdfAction::class)->handle($class, $exam);

    $pageCount = preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
    expect($pageCount)->toBe(1);
});

it('aborts with 404 when no admit card has been generated yet for the class and exam', function () {
    $class = Classes::create(['name' => 'Combined Admit Class Empty', 'order' => 1]);
    $exam = makeCombinedAdmitCardTestExam($class->id);

    expect(fn () => app(BuildCombinedClassAdmitCardsPdfAction::class)->handle($class, $exam))
        ->toThrow(NotFoundHttpException::class);
});

it('streams the combined pdf as a download through the http route', function () {
    $admin = grantSuperAdmin(User::factory()->create(['user_type' => UserType::Admin, 'is_active' => true]));
    $class = Classes::create(['name' => 'Combined Admit Class Route', 'order' => 1]);
    $exam = makeCombinedAdmitCardTestExam($class->id);

    $student = makeCombinedAdmitCardTestStudent($class->id, 1);
    AdmitCard::create([
        'student_id' => $student->id,
        'exam_id' => $exam->id,
        'is_generated' => true,
        'page_size' => 'A4',
    ]);

    $response = $this->actingAs($admin)->get(route('admit-cards.class.download', ['class' => $class->id, 'exam' => $exam->id]));

    $response->assertStatus(Response::HTTP_OK);
    $response->assertHeader('Content-Type', 'application/pdf');
});
