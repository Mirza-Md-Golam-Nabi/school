<?php

namespace App\Models;

use App\Support\SchoolSettingStore;
use Database\Factories\SchoolSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    /** @use HasFactory<SchoolSettingFactory> */
    use HasFactory;

    public const HEAD_TITLE_SETTING = 'head_signatory_title';

    public const HEAD_TITLE_BN_SETTING = 'head_signatory_title_bn';

    public const DEFAULT_HEAD_TITLE = 'Principal';

    public const DEFAULT_HEAD_TITLE_BN = 'প্রধান শিক্ষক';

    public const EIIN_SETTING = 'school_eiin';

    public const SCHOOL_NAME_BN_SETTING = 'school_name_bn';

    public const SCHOOL_ADDRESS_BN_SETTING = 'school_address_bn';

    public const SCHOOL_CODE_SETTING = 'school_code';

    public const DEFAULT_SCHOOL_CODE = 1;

    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        static::saved(fn () => app(SchoolSettingStore::class)->forget());
        static::deleted(fn () => app(SchoolSettingStore::class)->forget());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return app(SchoolSettingStore::class)->get($key, $default);
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * The school head's title printed under their signature on documents
     * (Principal, Head Teacher, Headmaster…) — Bengali when the locale is bn.
     */
    public static function headSignatoryTitle(): string
    {
        $title = app()->getLocale() === 'bn'
            ? static::get(self::HEAD_TITLE_BN_SETTING) ?: static::get(self::HEAD_TITLE_SETTING)
            : static::get(self::HEAD_TITLE_SETTING);

        if (filled($title)) {
            return $title;
        }

        return app()->getLocale() === 'bn' ? self::DEFAULT_HEAD_TITLE_BN : self::DEFAULT_HEAD_TITLE;
    }

    /**
     * The school's name printed on documents — the বাংলা name when the locale
     * is bn and one is set, else the English name.
     */
    public static function schoolName(): string
    {
        return (string) static::localizedSetting('school_name', self::SCHOOL_NAME_BN_SETTING);
    }

    /**
     * The school's address printed on documents — the বাংলা address when the
     * locale is bn and one is set, else the English address.
     */
    public static function schoolAddress(): string
    {
        return (string) static::localizedSetting('school_address', self::SCHOOL_ADDRESS_BN_SETTING);
    }

    private static function localizedSetting(string $englishKey, string $bengaliKey): mixed
    {
        if (app()->getLocale() === 'bn' && filled($bengali = static::get($bengaliKey))) {
            return $bengali;
        }

        return static::get($englishKey, '');
    }

    /**
     * The 3-digit school code that prefixes every Student ID (1–999): the one
     * set in School Settings, else a numeric CENTRAL_SCHOOL_ID, else 1.
     */
    public static function schoolCode(): int
    {
        foreach ([static::get(self::SCHOOL_CODE_SETTING), config('central.school_id')] as $candidate) {
            $candidate = trim((string) $candidate);

            if (ctype_digit($candidate) && (int) $candidate >= 1 && (int) $candidate <= 999) {
                return (int) $candidate;
            }
        }

        return self::DEFAULT_SCHOOL_CODE;
    }

    /**
     * The "EIIN: …" line printed under the school name on documents, or an
     * empty string when no EIIN number is set.
     */
    public static function eiinLine(): string
    {
        $eiin = trim((string) static::get(self::EIIN_SETTING, ''));

        return $eiin !== '' ? "EIIN: {$eiin}" : '';
    }
}
