<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Http\RedirectResponse;

/**
 * Sends users of every Filament panel (admin, teacher, student) back to the
 * public home page after logging out, instead of the panel's login page.
 */
class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        return redirect()->route('home');
    }
}
