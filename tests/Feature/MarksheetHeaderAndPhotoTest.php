<?php

use App\Actions\BuildStudentMarksDetail;
use App\Enums\AdmitCardDesign;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\SubjectType;
use App\Enums\UserType;
use App\Models\AdmitCard;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\ExamType;
use App\Models\Marksheet;
use App\Models\SchoolSetting;
use App\Models\StudentMeritRanking;
use App\Models\StudentProfile;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

function makePhotoTestStudent(?string $photo, Gender $gender = Gender::Male): array
{
    $class = Classes::create(['name' => 'Class One', 'order' => 1]);
    $exam = Exam::create([
        'exam_type_id' => ExamType::create(['name' => 'Half Yearly', 'is_active' => true])->id,
        'class_id' => $class->id,
        'session_year' => now()->year,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'is_published' => true,
    ]);

    $student = StudentProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Student, 'is_active' => true])->id,
        'roll_no' => 1,
        'current_class_id' => $class->id,
        'session_year' => now()->year,
        'gender' => $gender,
        'status' => StudentStatus::Active,
        'photo' => $photo,
    ]);

    return [$class, $exam, $student];
}

function renderPhotoTestMarksheet(?string $photo, Gender $gender = Gender::Male): string
{
    [$class, $exam, $student] = makePhotoTestStudent($photo, $gender);

    $subject = Subject::create(['name' => 'Bangla', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);
    $subject->classes()->attach($class->id, ['subject_type' => SubjectType::Compulsory->value]);
    ExamSubjectConfig::create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'written_total' => 100, 'total_marks' => 100, 'pass_mark' => 33]);
    StudentResult::create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'class_id' => $class->id, 'student_id' => $student->id, 'written_marks' => 80]);
    $ranking = StudentMeritRanking::create(['exam_id' => $exam->id, 'student_id' => $student->id, 'class_id' => $class->id, 'total_marks' => 80, 'gpa' => 4.0, 'class_rank' => 1]);

    $marksheet = Marksheet::create(['student_id' => $student->id, 'exam_id' => $exam->id]);
    ['rows' => $rows, 'summary' => $summary] = app(BuildStudentMarksDetail::class)->handle($ranking);

    return view('documents.marksheet', compact('marksheet', 'rows', 'summary'))->render();
}

function storePhotoTestImage(string $name, string $directory): string
{
    return UploadedFile::fake()->image($name, 120, 150)->store($directory, 'public');
}

it('puts the school logo above the school name with a border under the header', function () {
    SchoolSetting::set('school_name', 'Test School');
    SchoolSetting::set('school_logo', storePhotoTestImage('logo.png', 'school-settings'));

    $html = renderPhotoTestMarksheet(null);

    expect($html)->toMatch('/<td class="header-side"><\/td>\s*<td>\s*<img class="logo"[^>]*>\s*<div class="school-name">Test School<\/div>/')
        ->and($html)->toMatch('/\.header \{[^}]*border-bottom/');
});

it('prints the student photo above the details in the left column, the title in the right', function () {
    $html = renderPhotoTestMarksheet(storePhotoTestImage('photo.jpg', 'student-profiles/photos'));

    expect($html)->toMatch('/<div class="student-col">\s*<img class="photo"[^>]*alt="Photo"[^>]*>\s*<table class="info">/')
        ->and($html)->toMatch('/<div class="title-col">\s*<div class="title">/')
        ->and($html)->toContain('<td class="label">Student ID</td>', '<td class="label">Student Name</td>');
});

it('puts the grade scale under the title, apart from the details, with a rounded title box', function () {
    $html = renderPhotoTestMarksheet(null);

    expect($html)->toMatch('/<div class="title-col">\s*<div class="title">.*?<\/div>\s*<table class="grade-scale">/s')
        ->and($html)->toMatch('/\.title \{[^}]*border-radius/');
});

it('prints a boy or girl silhouette when the student has no photo', function (Gender $gender, string $placeholder) {
    $html = renderPhotoTestMarksheet(null, $gender);

    expect($html)->toMatch('/<div class="student-col">\s*<img class="photo" src="[^"]*'.preg_quote($placeholder, '/').'"/');
})->with([
    'male' => [Gender::Male, 'student-male.svg'],
    'female' => [Gender::Female, 'student-female.svg'],
    'other' => [Gender::Other, 'student-male.svg'],
]);

it('prints the uploaded photo, not the silhouette, when the student has one', function () {
    $html = renderPhotoTestMarksheet(storePhotoTestImage('photo.jpg', 'student-profiles/photos'), Gender::Female);

    expect($html)->not->toContain('student-female.svg', 'student-male.svg');
});

it('prints class and roll no side by side in the student details', function () {
    $html = renderPhotoTestMarksheet(null);

    expect($html)->toMatch('/<td class="label">Class<\/td>\s*<td\s*>Class One<\/td>\s*<td class="label">Roll No<\/td>\s*<td\s*>01<\/td>/');
});

it('prints the student photo on the simple admit card, or a box to paste one', function (bool $hasPhoto) {
    [, $exam, $student] = makePhotoTestStudent($hasPhoto ? storePhotoTestImage('photo.jpg', 'student-profiles/photos') : null);
    $admitCard = AdmitCard::create(['student_id' => $student->id, 'exam_id' => $exam->id, 'page_size' => 'A4', 'design' => AdmitCardDesign::Simple]);

    $html = view('documents.admit-card', ['admitCard' => $admitCard])->render();

    expect($html)->toContain('class="photo-cell"');

    if ($hasPhoto) {
        expect($html)->toContain('alt="Photo"')->not->toContain('class="photo-placeholder"');
    } else {
        expect($html)->toContain('class="photo-placeholder"')->not->toContain('alt="Photo"');
    }
})->with([
    'with a photo' => [true],
    'without a photo' => [false],
]);

it('prints a full marks column after the subject, then written, mcq and marks', function () {
    $html = renderPhotoTestMarksheet(null);

    expect($html)->toMatch('/>Subject<\/th>\s*<th[^>]*>Full Marks<\/th>\s*<th[^>]*>Written<\/th>\s*<th[^>]*>MCQ<\/th>\s*<th[^>]*>Marks<\/th>\s*<th[^>]*>Best<\/th>\s*<th[^>]*>Grade<\/th>\s*<th[^>]*>GP<\/th>/')
        ->and($html)->toMatch('/<td[^>]*>Bangla<\/td>\s*<td[^>]*>100<\/td>\s*<td[^>]*>80<\/td>\s*<td[^>]*>-<\/td>/');
});
