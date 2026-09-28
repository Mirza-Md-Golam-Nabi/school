<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\ApplyDeviceSyncReportAction;
use App\Http\Requests\StoreDeviceSyncReportRequest;
use Illuminate\Http\JsonResponse;

class DeviceSyncReportController extends Controller
{
    /**
     * Receives what a sync client found on (and removed from) the device.
     */
    public function __invoke(StoreDeviceSyncReportRequest $request, ApplyDeviceSyncReportAction $action): JsonResponse
    {
        return response()->json($action->handle(
            $request->attributes->get('attendance_device'),
            $request->validated(),
        ));
    }
}
