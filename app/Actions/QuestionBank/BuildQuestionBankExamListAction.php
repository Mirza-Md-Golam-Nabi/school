<?php

namespace App\Actions\QuestionBank;

use App\Models\Exam;
use App\Models\ExamSubjectConfig;

class BuildQuestionBankExamListAction
{
    /**
     * একটা ক্লাসের পরীক্ষাগুলো (নতুনটা আগে), প্রতিটার যে বিষয়ে MCQ আছে (mcq_total > 0) শুধু সেগুলো —
     * Question Bank-এর "রেজাল্টে পাঠান"-এ কোন পরীক্ষার কোন বিষয়ে নম্বর যাবে, তা বাছার জন্য।
     * প্রকাশিত পরীক্ষাও আসে, তবে `is_published` দিয়ে চেনা যায় — সেখানে নম্বর বসানো যায় না।
     *
     * @return list<array{id: int, name: string, session_year: ?int, is_published: bool, subjects: list<array{id: int, name: string, name_bn: ?string, mcq_total: float}>}>
     */
    public function handle(int $classId): array
    {
        return Exam::query()
            ->where('class_id', $classId)
            ->with(['examType', 'class', 'subjectConfigs' => fn ($configs) => $configs->where('mcq_total', '>', 0)->with('subject')])
            ->orderByDesc('session_year')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Exam $exam): array => [
                'id' => $exam->id,
                'name' => $exam->displayLabel(),
                'session_year' => $exam->session_year,
                'is_published' => (bool) $exam->is_published,
                'subjects' => $exam->subjectConfigs
                    ->filter(fn (ExamSubjectConfig $config): bool => $config->subject !== null)
                    ->map(fn (ExamSubjectConfig $config): array => [
                        'id' => $config->subject_id,
                        'name' => $config->subject->name,
                        'name_bn' => $config->subject->name_bn,
                        'mcq_total' => (float) $config->mcq_total,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
