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
}
