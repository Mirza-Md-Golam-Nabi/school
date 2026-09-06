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
            ['name' => 'Play', 'level' => ClassLevel::PrePrimary, 'order' => -2],
            ['name' => 'Nursery', 'level' => ClassLevel::PrePrimary, 'order' => -1],
            ['name' => 'K.G', 'level' => ClassLevel::PrePrimary, 'order' => 0],
        ];
    }

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
                'level' => ClassLevel::College,
                'order' => $order,
                'has_group' => true,
            ])
            ->all();
    }
}
