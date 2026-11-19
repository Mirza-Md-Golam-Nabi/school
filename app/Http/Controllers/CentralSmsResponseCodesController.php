<?php

namespace App\Http\Controllers;

use App\Actions\Sms\SyncSmsResponseCodesAction;
use App\Http\Requests\SyncSmsResponseCodesRequest;
use Illuminate\Http\JsonResponse;

class CentralSmsResponseCodesController extends Controller
{
    /**
     * Replaces this school's SMS provider response codes with the central app's list.
     */
    public function __invoke(SyncSmsResponseCodesRequest $request, SyncSmsResponseCodesAction $action): JsonResponse
    {
        return response()->json([
            'synced' => $action->handle($request->validated('codes')),
        ]);
    }
}
