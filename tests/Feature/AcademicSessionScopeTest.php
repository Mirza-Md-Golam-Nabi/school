<?php

use App\Enums\ClassLevel;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\UserType;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TeacherSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->college = Classes::create(['name' => 'Class 11', 'order' => 11, 'level' => ClassLevel::College, 'is_active' => true]);
    $this->school = Classes::create(['name' => 'Class 9', 'order' => 9, 'level' => ClassLevel::Secondary, 'is_active' => true]);

    $this->examType = ExamType::create(['name' => 'Half Yearly', 'is_active' => true]);

    $this->makeExam = fn (Classes $class, int $sessionYear): Exam => Exam::create([
        'exam_type_id' => $this->examType->id,
        'class_id' => $class->id,
        'session_year' => $sessionYear,
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-10',
        'is_published' => false,
    ]);
});

it('keeps each class on its own current session between January and June', function () {
    $this->travelTo(Carbon::parse('2027-02-10'));

    $college2026 = ($this->makeExam)($this->college, 2026);
    $college2027 = ($this->makeExam)($this->college, 2027);
    $school2026 = ($this->makeExam)($this->school, 2026);
    $school2027 = ($this->makeExam)($this->school, 2027);

    expect(Exam::query()->inCurrentSession()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$college2026->id, $school2027->id])->sort()->values()->all());
});

it('uses the same session for every class between July and December', function () {
    $this->travelTo(Carbon::parse('2026-09-10'));

    $college2026 = ($this->makeExam)($this->college, 2026);
    ($this->makeExam)($this->college, 2025);
    $school2026 = ($this->makeExam)($this->school, 2026);

    expect(Exam::query()->inCurrentSession()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$college2026->id, $school2026->id])->sort()->values()->all());
});

it('treats classes without a level as school classes', function () {
    $this->travelTo(Carbon::parse('2027-02-10'));

    $unlevelled = Classes::create(['name' => 'Class 5', 'order' => 5, 'is_active' => true]);
    ($this->makeExam)($unlevelled, 2026);
    $current = ($this->makeExam)($unlevelled, 2027);

    expect(Exam::query()->inCurrentSession()->pluck('id')->all())->toBe([$current->id]);
});

it('shows a college teacher assignment made in July through the following June', function () {
    $teacher = TeacherProfile::create([
        'user_id' => User::factory()->create(['user_type' => UserType::Teacher, 'is_active' => true])->id,
        'gender' => Gender::Male,
        'status' => EmploymentStatus::Active,
    ]);
    $subject = Subject::create(['name' => 'Physics', 'has_mcq' => false, 'has_written' => true, 'has_practical' => false, 'is_active' => true]);

    $assignment = TeacherSubject::create([
        'teacher_id' => $teacher->id,
        'class_id' => $this->college->id,
        'subject_id' => $subject->id,
        'session_year' => 2026,
    ]);

    $this->travelTo(Carbon::parse('2027-05-20'));
    expect(TeacherSubject::query()->inCurrentSession()->pluck('id')->all())->toBe([$assignment->id]);

    $this->travelTo(Carbon::parse('2027-07-01'));
    expect(TeacherSubject::query()->inCurrentSession()->exists())->toBeFalse();
});
