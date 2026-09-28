<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\IngestDevicePunchesAction;
use App\Http\Requests\StoreDevicePunchesRequest;
use Illuminate\Http\JsonResponse;

class DevicePunchController extends Controller
{
    /**
     * Receives a batch of raw punches from an attendance device's sync client.
     */
    public function __invoke(StoreDevicePunchesRequest $request, IngestDevicePunchesAction $action): JsonResponse
    {
        $result = $action->handle(
            $request->attributes->get('attendance_device'),
            $request->validated('punches'),
        );

        return response()->json($result);
    }
}
