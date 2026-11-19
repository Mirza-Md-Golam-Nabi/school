<?php

namespace App\Http\Controllers;

use App\Actions\Sms\SyncSmsVendorsAction;
use App\Http\Requests\SyncSmsVendorsRequest;
use Illuminate\Http\JsonResponse;

class CentralSmsVendorsController extends Controller
{
    /**
     * Replaces this school's SMS vendors with the central app's list.
     */
    public function __invoke(SyncSmsVendorsRequest $request, SyncSmsVendorsAction $action): JsonResponse
    {
        return response()->json([
            'synced' => $action->handle($request->vendors()),
        ]);
    }
}
