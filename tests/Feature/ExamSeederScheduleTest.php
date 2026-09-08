<?php

use App\Models\Classes;
use App\Models\ClassGroupSubject;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\ExamType;
use Database\Seeders\ExamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function class9HalfYearlyExam(): Exam
{
    $sessionYear = (int) now()->year;
    $class9 = Classes::where('name', 'Class 9')->firstOrFail();
    $halfYearlyTypeId = ExamType::where('name', 'Half Yearly')->value('id');

    return Exam::where('exam_type_id', $halfYearlyTypeId)
        ->where('class_id', $class9->id)
        ->where('session_year', $sessionYear)
        ->firstOrFail();
}

it('creates an exam schedule row for every subject attached to the class, including group-exclusive ones', function () {
    test()->seed();

    $class9 = Classes::where('name', 'Class 9')->firstOrFail();
    $expectedSubjectCount = ClassGroupSubject::where('class_id', $class9->id)->pluck('subject_id')->unique()->count();

    $exam = class9HalfYearlyExam();
    $scheduleCount = ExamSchedule::where('exam_id', $exam->id)->count();

    expect($expectedSubjectCount)->toBeGreaterThan(7) // sanity: more than just the all-groups subjects
        ->and($scheduleCount)->toBe($expectedSubjectCount);

    // Group-exclusive subjects (Physics for Science, Accounting for Commerce) must be scheduled too.
    $scheduledSubjectNames = ExamSchedule::where('exam_id', $exam->id)->with('subject')->get()->pluck('subject.name');

    expect($scheduledSubjectNames)->toContain('Physics')
        ->and($scheduledSubjectNames)->toContain('Accounting')
        ->and($scheduledSubjectNames)->toContain('Biology');
});

it('never schedules an exam on a friday or saturday', function () {
    test()->seed();

    $examDates = ExamSchedule::all()->pluck('exam_date');

    expect($examDates)->not->toBeEmpty();

    foreach ($examDates as $date) {
        expect($date->isFriday())->toBeFalse()
            ->and($date->isSaturday())->toBeFalse();
    }
});

it('gives every subject a distinct exam date within the same exam', function () {
    test()->seed();

    $exam = class9HalfYearlyExam();
    $dates = ExamSchedule::where('exam_id', $exam->id)->pluck('exam_date')->map(fn ($d) => $d->toDateString());

    expect($dates->unique()->count())->toBe($dates->count());
});

it("sets the exam's start_date and end_date to the actual first and last scheduled dates", function () {
    test()->seed();

    $exam = class9HalfYearlyExam();
    $scheduleDates = ExamSchedule::where('exam_id', $exam->id)->pluck('exam_date')->map(fn ($d) => $d->toDateString())->sort()->values();

    expect($exam->start_date->toDateString())->toBe($scheduleDates->first())
        ->and($exam->end_date->toDateString())->toBe($scheduleDates->last());
});

it('is idempotent — running the seeder twice keeps the same schedule dates and row count', function () {
    test()->seed();

    $exam = class9HalfYearlyExam();
    $before = ExamSchedule::where('exam_id', $exam->id)->orderBy('subject_id')->pluck('exam_date', 'subject_id');

    (new ExamSeeder)->run();

    $exam->refresh();
    $after = ExamSchedule::where('exam_id', $exam->id)->orderBy('subject_id')->pluck('exam_date', 'subject_id');

    expect($after->count())->toBe($before->count());

    foreach ($before as $subjectId => $date) {
        expect($after[$subjectId]->toDateString())->toBe($date->toDateString());
    }
});
