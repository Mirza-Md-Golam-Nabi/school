<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        $identifier = $event->credentials['email']
            ?? $event->credentials['username']
            ?? $event->credentials['phone']
            ?? null;

        activity('auth')
            ->causedBy($event->user)
            ->withProperties([
                'guard' => $event->guard,
                'identifier' => $identifier,
            ])
            ->event('failed_login')
            ->log('Failed login attempt.');
    }
}
