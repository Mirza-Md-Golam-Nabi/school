<?php

namespace App\Filament\Resources\StudentProfiles\Widgets;

use App\Enums\StudentStatus;
use App\Models\Group;
use App\Models\StudentProfile;
use App\Support\ClassGroupSubjectDefinitions;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GroupStudentCountsWidget extends StatsOverviewWidget
{
    public int $classId = 0;

    protected static bool $isLazy = false;

    protected int|array|null $columns = [
        'default' => 2,
        'lg' => 3,
    ];

    protected function getStats(): array
    {
        $groupIds = Group::whereIn('name', ClassGroupSubjectDefinitions::SECONDARY_GROUPS)->pluck('id', 'name');

        $countsByGroupId = StudentProfile::query()
            ->where('current_class_id', $this->classId)
            ->where('session_year', now()->year)
            ->where('status', StudentStatus::Active)
            ->whereIn('current_group_id', $groupIds->values())
            ->selectRaw('current_group_id, count(*) as total')
            ->groupBy('current_group_id')
            ->pluck('total', 'current_group_id');

        $countFor = fn (string $groupName): int => (int) ($countsByGroupId[$groupIds[$groupName] ?? 0] ?? 0);

        // Keeps the cards short on both mobile and laptop — Filament's default
        // stat padding is too tall for a 2-3-up compact summary row.
        $compactClass = '!py-2.5 !px-3 sm:!py-3 sm:!px-4';

        return [
            Stat::make('Science', $countFor('Science'))
                ->icon('heroicon-o-beaker')
                ->color('info')
                ->extraAttributes([
                    'class' => "{$compactClass} border-l-4 border-blue-400 !bg-blue-50 dark:!bg-blue-950/30",
                ]),

            Stat::make('Commerce', $countFor('Commerce'))
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->extraAttributes([
                    'class' => "{$compactClass} border-l-4 border-amber-400 !bg-amber-50 dark:!bg-amber-950/30",
                ]),

            Stat::make('Humanities', $countFor('Humanities'))
                ->icon('heroicon-o-book-open')
                ->color('success')
                ->extraAttributes([
                    'class' => "{$compactClass} border-l-4 border-green-400 !bg-green-50 dark:!bg-green-950/30",
                ]),
        ];
    }
}
