<?php

namespace App\Http\Controllers;

use App\Actions\Central\BuildStudentCountReportAction;
use Illuminate\Http\JsonResponse;

class CentralStudentReportController extends Controller
{
    /**
     * Gives the central app this school's current student count whenever it asks.
     */
    public function __invoke(BuildStudentCountReportAction $action): JsonResponse
    {
        return response()->json($action->handle());
    }
}
