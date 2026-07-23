<?php

namespace App\Filament\Teacher\Pages;

use App\Enums\LeaveApplicationStatus;
use App\Models\LeaveApplication;
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

        $takenByType = LeaveApplication::query()
            ->where('applicant_type', TeacherProfile::class)
            ->where('applicant_id', $profile->id)
            ->where('status', LeaveApplicationStatus::Approved)
            ->whereYear('from_date', now()->year)
            ->selectRaw('leave_type_id, sum(total_days) as taken_days')
            ->groupBy('leave_type_id')
            ->pluck('taken_days', 'leave_type_id');

        $rows = LeaveType::query()
            ->active()
            ->availableFor($profile)
            ->orderBy('name')
            ->get()
            ->map(function (LeaveType $leaveType) use ($takenByType) {
                $taken = (int) ($takenByType[$leaveType->id] ?? 0);

                return [
                    'name' => $leaveType->name,
                    'applicable_gender' => $leaveType->applicable_gender,
                    'allowed' => $leaveType->allowed_days_per_year,
                    'taken' => $taken,
                    'remaining' => max(0, $leaveType->allowed_days_per_year - $taken),
                ];
            });

        return ['rows' => $rows];
    }

    private function resolveProfile(): ?TeacherProfile
    {
        return auth()->user()?->teacherProfile;
    }
}
