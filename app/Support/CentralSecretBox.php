<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;

/**
 * Opens secrets (e.g. an SMS provider's API key) that the central app sealed
 * for this school only. The key is derived from this school's central secret,
 * so a captured request, a proxy log or another school can't read them —
 * on top of HTTPS and the body signature.
 */
class CentralSecretBox
{
    /**
     * @throws DecryptException when the value was not sealed with this school's secret
     */
    public static function open(string $sealed): string
    {
        return self::encrypter()->decryptString($sealed);
    }

    /**
     * Seals a value the way the central app does. Used by tests.
     */
    public static function seal(string $value): string
    {
        return self::encrypter()->encryptString($value);
    }

    private static function encrypter(): Encrypter
    {
        $key = hash_hmac('sha256', 'sms-vendor-api-key', (string) config('central.secret'), true);

        return new Encrypter($key, 'aes-256-gcm');
    }
}
