<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->must_change_password) {
            return $next($request);
        }

        $profileUrl = Filament::getCurrentPanel()?->getProfileUrl();
        $profilePath = $profileUrl ? ltrim((string) parse_url($profileUrl, PHP_URL_PATH), '/') : null;

        if (! $profilePath || $request->is($profilePath)) {
            return $next($request);
        }

        return redirect($profileUrl);
    }
}
