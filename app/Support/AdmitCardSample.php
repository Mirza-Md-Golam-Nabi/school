<?php

namespace App\Support;

use App\Enums\AdmitCardDesign;
use App\Models\AdmitCard;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\ExamType;
use App\Models\Section;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * A made-up admit card — student, exam and a 7-subject routine — for the
 * sample the admin sees before choosing a design. Built from unsaved models,
 * so nothing is written to the database; the school's own name, logo, seal
 * and signature still come from School Settings.
 */
class AdmitCardSample
{
    private const SUBJECTS = [
        'Bangla',
        'English',
        'Mathematics',
        'General Science',
        'Bangladesh & Global Studies',
        'Religious Studies',
        'ICT',
    ];

    public static function make(AdmitCardDesign $design): AdmitCard
    {
        $firstDay = Carbon::create(now()->year, 1, 4);

        $schedules = collect(self::SUBJECTS)->map(fn (string $name, int $index): ExamSchedule => (new ExamSchedule([
            'exam_date' => $firstDay->copy()->addDays($index)->toDateString(),
        ]))->forceFill(['id' => $index + 1])->setRelation('subject', new Subject(['name' => $name])));

        $exam = (new Exam([
            'session_year' => now()->year,
            'start_date' => $firstDay->toDateString(),
            'end_date' => $firstDay->copy()->addDays(count(self::SUBJECTS) - 1)->toDateString(),
        ]))
            ->setRelation('examType', new ExamType(['name' => 'Half Yearly']))
            ->setRelation('schedules', $schedules);

        $student = (new StudentProfile([
            'roll_no' => 7,
            'registration_no' => '2504000142',
        ]))
            ->setRelation('user', new User(['name' => 'Ayesha Siddika']))
            ->setRelation('class', new Classes(['name' => 'Class Six']))
            ->setRelation('section', new Section(['name' => 'A']))
            ->setRelation('group', null);

        return (new AdmitCard(['page_size' => 'A5', 'design' => $design]))
            ->setRelation('student', $student)
            ->setRelation('exam', $exam);
    }
}
