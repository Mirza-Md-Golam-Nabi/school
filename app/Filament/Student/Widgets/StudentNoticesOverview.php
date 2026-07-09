<?php

namespace App\Filament\Student\Widgets;

use App\Filament\Student\Concerns\InteractsWithNotices;
use App\Filament\Student\Pages\Notices;
use App\Models\Notice;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class StudentNoticesOverview extends Widget
{
    use InteractsWithNotices;

    protected string $view = 'filament.student.widgets.student-notices-overview';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        $student = $this->currentStudent();

        if (! $student) {
            return ['notices' => collect(), 'url' => Notices::getUrl(panel: 'student')];
        }

        /** @var User $user */
        $user = Auth::user();

        $notices = Notice::query()
            ->published()
            ->visibleToStudent($student)
            ->withExists(['reads as is_read' => fn ($query) => $query->where('user_id', $user->id)])
            ->latest('published_at')
            ->limit(3)
            ->get();

        return [
            'notices' => $notices,
            'url' => Notices::getUrl(panel: 'student'),
        ];
    }
}
