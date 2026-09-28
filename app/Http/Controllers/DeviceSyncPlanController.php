<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\BuildDeviceSyncPlanAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceSyncPlanController extends Controller
{
    /**
     * Tells a device's sync client which users to create on the device and which to delete.
     */
    public function __invoke(Request $request, BuildDeviceSyncPlanAction $action): JsonResponse
    {
        return response()->json($action->handle($request->attributes->get('attendance_device')));
    }
}
