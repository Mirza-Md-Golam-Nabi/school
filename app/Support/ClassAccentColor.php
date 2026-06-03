<?php

namespace App\Support;

class ClassAccentColor
{
    private const COLORS = [
        '#6366f1',
        '#22c55e',
        '#f59e0b',
        '#ef4444',
        '#06b6d4',
        '#8b5cf6',
        '#ec4899',
        '#f97316',
        '#14b8a6',
        '#84cc16',
    ];

    public static function for(int $classId): string
    {
        return self::COLORS[$classId % count(self::COLORS)];
    }
}
