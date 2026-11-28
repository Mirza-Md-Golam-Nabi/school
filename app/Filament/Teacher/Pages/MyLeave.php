<?php

namespace App\Filament\Teacher\Pages;

use App\Actions\Leave\ResolveLeaveUsageAction;
use App\Models\LeaveType;
use App\Models\TeacherProfile;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class MyLeave extends Page
{
    protected string $view = 'filament.teacher.pages.my-leave';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Leave';

    protected static ?string $navigationLabel = 'My Leave';

    protected static ?int $navigationSort = 4;

    public function getViewData(): array
    {
        $profile = $this->resolveProfile();

        if (! $profile || ! $profile->gender) {
            return ['rows' => collect()];
        }

        $resolveUsage = app(ResolveLeaveUsageAction::class);

        $rows = LeaveType::query()
            ->active()
            ->availableFor($profile)
            ->orderBy('name')
            ->get()
            ->map(function (LeaveType $leaveType) use ($profile, $resolveUsage) {
                $usage = $resolveUsage->handle(TeacherProfile::class, $profile->id, $leaveType, now()->year);

                return [
                    'name' => $leaveType->name,
                    'applicable_gender' => $leaveType->applicable_gender,
                    'allowed' => $usage['allowed'],
                    'taken' => $usage['taken'],
                    'remaining' => max(0, $usage['allowed'] - $usage['taken']),
                    'excess' => $usage['excess'],
                ];
            });

        return ['rows' => $rows];
    }

    private function resolveProfile(): ?TeacherProfile
    {
        return auth()->user()?->teacherProfile;
    }
}
