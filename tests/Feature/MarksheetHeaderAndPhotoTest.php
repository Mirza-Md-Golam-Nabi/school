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

function makePhotoTestStudent(?string $photo): array
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
        'gender' => Gender::Male,
        'status' => StudentStatus::Active,
        'photo' => $photo,
    ]);

    return [$class, $exam, $student];
}

function renderPhotoTestMarksheet(?string $photo): string
{
    [$class, $exam, $student] = makePhotoTestStudent($photo);

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

it('puts the school logo in the left cell of the marksheet header, the school name in the middle', function () {
    SchoolSetting::set('school_name', 'Test School');
    SchoolSetting::set('school_logo', storePhotoTestImage('logo.png', 'school-settings'));

    $html = renderPhotoTestMarksheet(null);

    expect($html)->toMatch('/<td class="header-side">\s*<img class="logo"[^>]*>\s*<\/td>\s*<td>\s*<div class="school-name">Test School<\/div>/');
});

it('prints the student photo on the marksheet when one is uploaded', function () {
    $html = renderPhotoTestMarksheet(storePhotoTestImage('photo.jpg', 'student-profiles/photos'));

    expect($html)->toContain('class="photo-cell"', 'alt="Photo"');
});

it('leaves the photo out of the marksheet when the student has none', function () {
    $html = renderPhotoTestMarksheet(null);

    expect($html)->not->toContain('class="photo-cell"', 'alt="Photo"');
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
