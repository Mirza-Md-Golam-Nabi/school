<?php

namespace App\Http\Controllers;

use App\Actions\QuestionBank\BuildQuestionBankClassListAction;
use Illuminate\Http\JsonResponse;

class QuestionBankClassesController extends Controller
{
    /**
     * Question Bank-কে এই স্কুলের চালু ক্লাস ও সেকশনের তালিকা দেয় — স্কুলের পরীক্ষায় ঢোকার ফর্মের জন্য।
     */
    public function __invoke(BuildQuestionBankClassListAction $action): JsonResponse
    {
        return response()->json(['classes' => $action->handle()]);
    }
}
