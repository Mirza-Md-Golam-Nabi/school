<?php

namespace App\Http\Middleware;

use App\Models\AttendanceDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAttendanceDevice
{
    /**
     * Authenticates a sync client by its per-device bearer token and exposes the
     * device as the "attendance_device" request attribute.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        $device = $token ? AttendanceDevice::findActiveByToken($token) : null;

        if (! $device) {
            return response()->json(['message' => 'Unauthenticated device.'], Response::HTTP_UNAUTHORIZED);
        }

        $request->attributes->set('attendance_device', $device);

        return $next($request);
    }
}
