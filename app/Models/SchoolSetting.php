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
     * The "EIIN: …" line printed under the school name on documents, or an
     * empty string when no EIIN number is set.
     */
    public static function eiinLine(): string
    {
        $eiin = trim((string) static::get(self::EIIN_SETTING, ''));

        return $eiin !== '' ? "EIIN: {$eiin}" : '';
    }
}
