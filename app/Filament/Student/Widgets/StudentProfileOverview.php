<?php

namespace App\Filament\Student\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\Auth;

class StudentProfileOverview extends StatsOverviewWidget
{
    protected string $view = 'filament.student.widgets.profile-overview';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        $user = Auth::user();
        $profile = $user->studentProfile()->with(['class', 'section', 'group'])->first();

        return [
            'name' => $user->name,
            'roll_no' => $profile?->roll_no ?? '—',
            'class' => $profile?->class?->name ?? '—',
            'section' => $profile?->section?->name ?? null,
            'group' => $profile?->group?->name ?? null,
        ];
    }
}
