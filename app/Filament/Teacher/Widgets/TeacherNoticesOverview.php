<?php

namespace App\Filament\Teacher\Widgets;

use App\Filament\Teacher\Concerns\InteractsWithNotices;
use App\Filament\Teacher\Pages\Notices;
use App\Models\Notice;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class TeacherNoticesOverview extends Widget
{
    use InteractsWithNotices;

    protected string $view = 'filament.teacher.widgets.teacher-notices-overview';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        $teacher = $this->currentTeacher();

        if (! $teacher) {
            return ['notices' => collect(), 'url' => Notices::getUrl(panel: 'teacher')];
        }

        /** @var User $user */
        $user = Auth::user();

        $notices = Notice::query()
            ->published()
            ->visibleToTeacher($teacher)
            ->withExists(['reads as is_read' => fn ($query) => $query->where('user_id', $user->id)])
            ->latest('published_at')
            ->limit(3)
            ->get();

        return [
            'notices' => $notices,
            'url' => Notices::getUrl(panel: 'teacher'),
        ];
    }
}
