<?php

namespace App\Filament\Student\Pages;

use App\Models\StudentClassHistory;
use App\Models\StudentProfile;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AcademicHistory extends Page
{
    protected string $view = 'filament.student.pages.academic-history';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'History';

    protected static ?string $navigationLabel = 'Academic History';

    /**
     * @return Collection<int, StudentClassHistory>
     */
    public function getHistories(): Collection
    {
        $profile = $this->getStudentProfile();

        if (! $profile) {
            return new Collection;
        }

        return StudentClassHistory::where('student_id', $profile->id)
            ->with('class')
            ->orderByDesc('session_year')
            ->get();
    }

    private function getStudentProfile(): ?StudentProfile
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->studentProfile;
    }
}
