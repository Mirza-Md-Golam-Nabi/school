<?php

namespace App\Filament\Teacher\Pages;

use App\Filament\Teacher\Concerns\InteractsWithNotices;
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

    protected string $view = 'filament.teacher.pages.notices';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Notices';

    protected static ?int $navigationSort = 0;

    /**
     * @return LengthAwarePaginator<int, Notice>
     */
    public function getNotices(): LengthAwarePaginator
    {
        $teacher = $this->currentTeacher();

        if (! $teacher) {
            return new LengthAwarePaginator([], 0, 10);
        }

        /** @var User $user */
        $user = Auth::user();

        return Notice::query()
            ->published()
            ->visibleToTeacher($teacher)
            ->withExists(['reads as is_read' => fn ($query) => $query->where('user_id', $user->id)])
            ->latest('published_at')
            ->paginate(10);
    }
}
