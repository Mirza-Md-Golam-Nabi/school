<?php

namespace App\Support;

use App\Models\SchoolSetting;

/**
 * Request-scoped copy of the school_settings table. A single page reads many
 * settings (school name, logo, weekend days, ID card layout…), so they are
 * loaded with one query instead of one query per key.
 *
 * Bound as a scoped singleton (see AppServiceProvider) and forgotten whenever
 * a SchoolSetting is saved or deleted, so it never serves stale values.
 */
class SchoolSettingStore
{
    /** @var array<string, mixed>|null */
    private ?array $settings = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $this->settings ??= SchoolSetting::query()->pluck('value', 'key')->all();

        return array_key_exists($key, $this->settings) ? $this->settings[$key] : $default;
    }

    public function forget(): void
    {
        $this->settings = null;
    }
}
