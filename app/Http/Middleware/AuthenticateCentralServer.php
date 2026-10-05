<?php

namespace App\Http\Middleware;

use App\Support\CentralSignature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCentralServer
{
    /**
     * Lets a request through only when the central app signed it with this
     * school's secret: X-Signature must be the HMAC-SHA256 of
     * "{X-Timestamp}.{this school's id}", and the timestamp must be recent.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isAuthentic = CentralSignature::verify(
            $request->header('X-Timestamp'),
            (string) config('central.school_id'),
            $request->header('X-Signature'),
        );

        if (! $isAuthentic) {
            return response()->json(['message' => 'Unauthenticated central server.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
