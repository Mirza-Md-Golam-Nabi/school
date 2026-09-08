<?php

use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Group;
use App\Models\StudentOptionalSubject;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use Database\Seeders\ClassSeeder;
use Database\Seeders\ClassSubjectSeeder;
use Database\Seeders\ExamConfigSeeder;
use Database\Seeders\ExamSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StudentResultSeeder;
use Database\Seeders\StudentSeeder;
use Database\Seeders\SubjectSeeder;
use Database\Seeders\TeacherSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedResultSeederPrerequisites(): void
{
    test()->seed(RoleSeeder::class);
    test()->seed(UserSeeder::class);
    test()->seed(TeacherSeeder::class);
    test()->seed(ClassSeeder::class);
    test()->seed(SubjectSeeder::class);
    test()->seed(ClassSubjectSeeder::class);
    test()->seed(StudentSeeder::class);
    test()->seed(ExamConfigSeeder::class);
    test()->seed(ExamSeeder::class);
    test()->seed(StudentResultSeeder::class);
}

function class9HalfYearlyResultExam(): Exam
{
    $sessionYear = (int) now()->year;
    $class9 = Classes::where('name', 'Class 9')->firstOrFail();
    $halfYearlyTypeId = ExamType::where('name', 'Half Yearly')->value('id');

    return Exam::where('exam_type_id', $halfYearlyTypeId)
        ->where('class_id', $class9->id)
        ->where('session_year', $sessionYear)
        ->firstOrFail();
}

it('only gives science-group students results for a science-only subject like Physics', function () {
    seedResultSeederPrerequisites();

    $exam = class9HalfYearlyResultExam();
    $physics = Subject::where('name', 'Physics')->firstOrFail();
    $scienceGroup = Group::where('name', 'Science')->firstOrFail();

    $resultStudentIds = StudentResult::where('exam_id', $exam->id)
        ->where('subject_id', $physics->id)
        ->pluck('student_id');

    expect($resultStudentIds)->not->toBeEmpty();

    foreach ($resultStudentIds as $studentId) {
        $student = StudentProfile::findOrFail($studentId);
        expect($student->current_group_id)->toBe($scienceGroup->id);
    }

    // Every science-group student in the class must actually have a Physics result.
    $scienceStudentIds = StudentProfile::where('current_class_id', $exam->class_id)
        ->where('current_group_id', $scienceGroup->id)
        ->active()
        ->pluck('id');

    expect($resultStudentIds->sort()->values()->all())->toBe($scienceStudentIds->sort()->values()->all());
});

it('never gives a non-science-group student a result for a science-only subject like Biology', function () {
    seedResultSeederPrerequisites();

    $exam = class9HalfYearlyResultExam();
    $biology = Subject::where('name', 'Biology')->firstOrFail();
    $scienceGroup = Group::where('name', 'Science')->firstOrFail();

    $resultStudentIds = StudentResult::where('exam_id', $exam->id)
        ->where('subject_id', $biology->id)
        ->pluck('student_id');

    $nonScienceStudentIds = StudentProfile::where('current_class_id', $exam->class_id)
        ->where('current_group_id', '!=', $scienceGroup->id)
        ->active()
        ->pluck('id');

    expect($resultStudentIds->intersect($nonScienceStudentIds))->toBeEmpty();
});

it('gives an optional-subject result only to the students who actually chose that subject', function () {
    seedResultSeederPrerequisites();

    $exam = class9HalfYearlyResultExam();

    // A subject in the optional pool (main or extra pick, either role) is only
    // sat by the students who recorded a choice for it — never the whole class.
    $optionalSelections = StudentOptionalSubject::where('class_id', $exam->class_id)
        ->get()
        ->groupBy('subject_id');

    expect($optionalSelections)->not->toBeEmpty();

    foreach ($optionalSelections as $subjectId => $selections) {
        $expectedStudentIds = $selections->pluck('student_id')->sort()->values()->all();

        $actualStudentIds = StudentResult::where('exam_id', $exam->id)
            ->where('subject_id', $subjectId)
            ->pluck('student_id')
            ->sort()
            ->values()
            ->all();

        expect($actualStudentIds)->toBe($expectedStudentIds);
    }
});
