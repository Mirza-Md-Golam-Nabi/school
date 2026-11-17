<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Traits\Localizable;

/**
 * Renders one part of a document in its own language — e.g. an admit card
 * whose student details are in বাংলা while its exam routine stays in English.
 * Inside the callback __(), Carbon's translatedFormat(), the models'
 * locale-aware display_name and BengaliNumber::localize() all follow the
 * chosen language; the previous locale is restored afterwards.
 */
class DocumentLocale
{
    use Localizable;

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public static function using(?string $language, Closure $callback): mixed
    {
        return (new self)->withLocale(self::normalize($language), $callback);
    }

    /**
     * An available locale ('en' / 'bn'), falling back to English.
     */
    public static function normalize(?string $language): string
    {
        return array_key_exists((string) $language, config('app.available_locales')) ? $language : 'en';
    }
}
