<?php

namespace App\Support;

/**
 * Signs and verifies "{timestamp}.{payload}" with HMAC-SHA256 under a shared
 * secret — the one scheme every server-to-server connection of this app
 * uses (the central app, the Question Bank). The secret never travels; a
 * request is trusted only if it is signed with it and is recent.
 */
class HmacSignature
{
    public static function sign(string $secret, string $timestamp, string $payload): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
    }

    /**
     * True when the signature matches and the timestamp is no older (or newer)
     * than `$toleranceSeconds`, so a captured request cannot be replayed later.
     */
    public static function verify(string $secret, ?string $timestamp, string $payload, ?string $signature, int $toleranceSeconds): bool
    {
        if (blank($secret) || blank($timestamp) || blank($signature) || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(now()->timestamp - (int) $timestamp) > $toleranceSeconds) {
            return false;
        }

        return hash_equals(self::sign($secret, $timestamp, $payload), $signature);
    }
}
