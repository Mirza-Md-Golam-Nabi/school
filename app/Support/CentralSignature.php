<?php

namespace App\Support;

/**
 * Signs and verifies the requests exchanged with the central app. Both sides
 * hold the same secret and sign "{timestamp}.{payload}" with HMAC-SHA256
 * (HmacSignature), so neither a forged report nor a forged pull request can
 * pass without it.
 */
class CentralSignature
{
    public static function isConfigured(): bool
    {
        return filled(config('central.school_id')) && filled(config('central.secret'));
    }

    public static function sign(string $timestamp, string $payload): string
    {
        return HmacSignature::sign((string) config('central.secret'), $timestamp, $payload);
    }

    /**
     * True when the signature matches and the timestamp is recent enough that
     * the request cannot be a replay of an old captured one.
     */
    public static function verify(?string $timestamp, string $payload, ?string $signature): bool
    {
        return self::isConfigured()
            && HmacSignature::verify((string) config('central.secret'), $timestamp, $payload, $signature, (int) config('central.signature_tolerance_seconds'));
    }
}
