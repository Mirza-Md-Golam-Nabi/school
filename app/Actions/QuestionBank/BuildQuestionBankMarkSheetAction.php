<?php

namespace App\Actions\QuestionBank;

use App\Actions\ResolveEligibleStudentsForSubject;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\StudentProfile;
use App\Models\StudentResult;

class BuildQuestionBankMarkSheetAction
{
    public function __construct(private ResolveEligibleStudentsForSubject $eligibleStudents) {}

    /**
     * একটা পরীক্ষার একটা বিষয়ের মার্কশিট, Question Bank যেভাবে মেলায়: বিষয়ের MCQ পূর্ণমান, পরীক্ষা
     * প্রকাশিত কি না, আর যে ছাত্ররা বিষয়টা নেয় (মার্ক এন্ট্রি পেজের একই নিয়মে) — রোল, নাম, সেকশন,
     * আর আগে থেকে থাকা MCQ নম্বর / অনুপস্থিত / অন্য (লিখিত, ব্যবহারিক) নম্বর আছে কি না।
     *
     * @return array{mcq_total: float, is_published: bool, students: list<array{id: int, roll: ?string, name: ?string, section_id: ?int, section_name: ?string, mcq_marks: ?float, is_absent: bool, has_other_marks: bool}>}
     */
    public function handle(Exam $exam, ExamSubjectConfig $config): array
    {
        $students = $this->eligibleStudents->execute($exam->class_id, $config->subject_id)->load('section');

        $results = StudentResult::query()
            ->where('exam_id', $exam->id)
            ->where('subject_id', $config->subject_id)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        return [
            'mcq_total' => (float) $config->mcq_total,
            'is_published' => (bool) $exam->is_published,
            'students' => $students->map(function (StudentProfile $student) use ($results): array {
                $result = $results->get($student->id);

                return [
                    'id' => $student->id,
                    'roll' => $student->roll_no !== null ? (string) $student->roll_no : null,
                    'name' => $student->user?->name,
                    'section_id' => $student->current_section_id,
                    'section_name' => $student->section?->name,
                    'mcq_marks' => $result?->mcq_marks,
                    'is_absent' => (bool) ($result?->is_absent ?? false),
                    'has_other_marks' => $result !== null && ($result->written_marks !== null || $result->practical_marks !== null),
                ];
            })->values()->all(),
        ];
    }
}
