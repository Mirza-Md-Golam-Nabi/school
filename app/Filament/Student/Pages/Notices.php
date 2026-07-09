<?php

namespace App\Filament\Student\Pages;

use App\Filament\Student\Concerns\InteractsWithNotices;
use App\Models\Notice;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

class Notices extends Page
{
    use InteractsWithNotices;
    use WithPagination;

    protected string $view = 'filament.student.pages.notices';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Notices';

    protected static ?int $navigationSort = 0;

    /**
     * @return LengthAwarePaginator<int, Notice>
     */
    public function getNotices(): LengthAwarePaginator
    {
        $student = $this->currentStudent();

        if (! $student) {
            return new LengthAwarePaginator([], 0, 10);
        }

        /** @var User $user */
        $user = Auth::user();

        return Notice::query()
            ->published()
            ->visibleToStudent($student)
            ->withExists(['reads as is_read' => fn ($query) => $query->where('user_id', $user->id)])
            ->latest('published_at')
            ->paginate(10);
    }
}
