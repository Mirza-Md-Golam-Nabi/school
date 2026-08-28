<?php

use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('publishes Half Yearly and the Tutorial rounds held before it, leaving later exams unpublished', function () {
    $this->seed();

    $sessionYear = (int) now()->year;
    $class = Classes::active()->firstOrFail();

    $halfYearlyTypeId = ExamType::where('name', 'Half Yearly')->value('id');
    $tutorialTypeId = ExamType::where('name', 'Tutorial')->value('id');
    $annualTypeId = ExamType::where('name', 'Annual')->value('id');

    $halfYearly = Exam::where('exam_type_id', $halfYearlyTypeId)
        ->where('class_id', $class->id)
        ->where('session_year', $sessionYear)
        ->firstOrFail();

    expect($halfYearly->is_published)->toBeTrue();

    $tutorials = Exam::where('exam_type_id', $tutorialTypeId)
        ->where('class_id', $class->id)
        ->where('session_year', $sessionYear)
        ->orderBy('start_date')
        ->get();

    expect($tutorials)->toHaveCount(4)
        ->and($tutorials[0]->is_published)->toBeTrue() // February — before Half Yearly
        ->and($tutorials[1]->is_published)->toBeTrue() // April — before Half Yearly
        ->and($tutorials[2]->is_published)->toBeFalse() // July — after Half Yearly
        ->and($tutorials[3]->is_published)->toBeFalse(); // September — after Half Yearly

    $annual = Exam::where('exam_type_id', $annualTypeId)
        ->where('class_id', $class->id)
        ->where('session_year', $sessionYear)
        ->firstOrFail();

    expect($annual->is_published)->toBeFalse();
});
