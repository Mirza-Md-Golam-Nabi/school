<?php

namespace App\Support;

/**
 * Signs and verifies the requests exchanged with the central app. Both sides
 * hold the same secret and sign "{timestamp}.{payload}" with HMAC-SHA256, so
 * neither a forged report nor a forged pull request can pass without it.
 */
class CentralSignature
{
    public static function isConfigured(): bool
    {
        return filled(config('central.school_id')) && filled(config('central.secret'));
    }

    public static function sign(string $timestamp, string $payload): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$payload}", (string) config('central.secret'));
    }

    /**
     * True when the signature matches and the timestamp is recent enough that
     * the request cannot be a replay of an old captured one.
     */
    public static function verify(?string $timestamp, string $payload, ?string $signature): bool
    {
        if (! self::isConfigured() || blank($timestamp) || blank($signature) || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(now()->timestamp - (int) $timestamp) > config('central.signature_tolerance_seconds')) {
            return false;
        }

        return hash_equals(self::sign($timestamp, $payload), $signature);
    }
}
