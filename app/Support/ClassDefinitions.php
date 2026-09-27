<?php

namespace App\Support;

use App\Enums\ClassLevel;

/**
 * Centralized default class list per level (pre-primary/primary/secondary/
 * college), shared by the `create:class` artisan command and any seeder that
 * needs the same data — so the two never drift out of sync.
 */
class ClassDefinitions
{
    /**
     * Pre-primary / kindergarten level — Play, Nursery and K.G. Government
     * schools don't have these. Ordered before Class 1 (negative/zero order)
     * so they sort correctly ahead of it, with no section or group.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function prePrimary(): array
    {
        return [
            ['name' => 'Play', 'name_bn' => 'প্লে', 'level' => ClassLevel::PrePrimary, 'order' => -2],
            ['name' => 'Nursery', 'name_bn' => 'নার্সারি', 'level' => ClassLevel::PrePrimary, 'order' => -1],
            ['name' => 'K.G', 'name_bn' => 'কে.জি', 'level' => ClassLevel::PrePrimary, 'order' => 0],
        ];
    }

    /**
     * Bengali ordinal class names ("প্রথম শ্রেণি", "দ্বিতীয় শ্রেণি", ...) used across
     * primary/secondary/college, keyed by class order — matches the naming
     * convention on Bangladeshi report cards/marksheets.
     *
     * @var array<int, string>
     */
    private const BENGALI_ORDINALS = [
        1 => 'প্রথম', 2 => 'দ্বিতীয়', 3 => 'তৃতীয়', 4 => 'চতুর্থ', 5 => 'পঞ্চম',
        6 => 'ষষ্ঠ', 7 => 'সপ্তম', 8 => 'অষ্টম', 9 => 'নবম', 10 => 'দশম',
        11 => 'একাদশ', 12 => 'দ্বাদশ',
    ];

    /**
     * Primary level — Class 1 through Class 5, no section or group.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function primary(): array
    {
        return collect(range(1, 5))
            ->map(fn (int $order): array => [
                'name' => "Class {$order}",
                'name_bn' => self::BENGALI_ORDINALS[$order],
                'level' => ClassLevel::Primary,
                'order' => $order,
            ])
            ->all();
    }

    /**
     * Secondary level — Class 6 through Class 10. All of them have sections,
     * but groups (Science/Arts/Commerce) only start from Class 9 onward.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function secondary(): array
    {
        return collect(range(6, 10))
            ->map(fn (int $order): array => [
                'name' => "Class {$order}",
                'name_bn' => self::BENGALI_ORDINALS[$order],
                'level' => ClassLevel::Secondary,
                'order' => $order,
                'has_section' => true,
                'has_group' => $order >= 9,
            ])
            ->all();
    }

    /**
     * College level — Class 11 and Class 12 (HSC 1st and 2nd year),
     * groups (Science/Arts/Commerce) are required here.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function college(): array
    {
        return collect(range(11, 12))
            ->map(fn (int $order): array => [
                'name' => "Class {$order}",
                'name_bn' => self::BENGALI_ORDINALS[$order],
                'level' => ClassLevel::College,
                'order' => $order,
                'has_group' => true,
            ])
            ->all();
    }
}
