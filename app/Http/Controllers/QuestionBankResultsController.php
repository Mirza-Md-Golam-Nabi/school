<?php

namespace App\Http\Controllers;

use App\Actions\QuestionBank\BuildQuestionBankExamListAction;
use App\Actions\QuestionBank\BuildQuestionBankMarkSheetAction;
use App\Actions\QuestionBank\SaveQuestionBankMcqMarksAction;
use App\Http\Requests\SaveQuestionBankMarksRequest;
use App\Models\Classes;
use App\Models\Exam;
use App\Models\ExamSubjectConfig;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class QuestionBankResultsController extends Controller
{
    /**
     * একটা ক্লাসের পরীক্ষা ও তার MCQ-ওয়ালা বিষয়গুলো।
     */
    public function exams(Classes $class, BuildQuestionBankExamListAction $action): JsonResponse
    {
        return response()->json(['exams' => $action->handle($class->id)]);
    }

    /**
     * একটা পরীক্ষার একটা বিষয়ের ছাত্র-তালিকা ও আগের নম্বর।
     */
    public function sheet(Exam $exam, Subject $subject, BuildQuestionBankMarkSheetAction $action): JsonResponse
    {
        return response()->json($action->handle($exam, $this->mcqConfig($exam, $subject)));
    }

    /**
     * Question Bank-এর MCQ নম্বর রেজাল্টে বসায়।
     */
    public function store(SaveQuestionBankMarksRequest $request, Exam $exam, Subject $subject, SaveQuestionBankMcqMarksAction $action): JsonResponse
    {
        $marks = collect($request->validated('marks'))
            ->map(fn (array $mark): array => [
                'student_id' => (int) $mark['student_id'],
                'absent' => (bool) $mark['absent'],
                'mcq_marks' => isset($mark['mcq_marks']) ? (float) $mark['mcq_marks'] : null,
            ])
            ->all();

        try {
            return response()->json($action->handle($exam, $this->mcqConfig($exam, $subject), $marks, (bool) $request->validated('overwrite')));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    private function mcqConfig(Exam $exam, Subject $subject): ExamSubjectConfig
    {
        return ExamSubjectConfig::query()
            ->where('exam_id', $exam->id)
            ->where('subject_id', $subject->id)
            ->where('mcq_total', '>', 0)
            ->firstOrFail();
    }
}
