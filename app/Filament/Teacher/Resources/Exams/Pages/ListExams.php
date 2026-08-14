<?php

namespace App\Filament\Teacher\Resources\Exams\Pages;

use App\Filament\Teacher\Concerns\ScopesToTaughtClasses;
use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Models\Classes;
use App\Models\Exam;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class ListExams extends Page
{
    use ScopesToTaughtClasses;

    protected static string $resource = ExamResource::class;

    protected string $view = 'filament.teacher.resources.exams.pages.list-exams';

    public Collection $classes;

    public function mount(): void
    {
        $this->classes = Classes::active()
            ->whereIn('id', static::currentTeacherTaughtClassIds())
            ->orderBy('order', 'asc')
            ->get()
            ->each(function ($class) {
                $stats = Exam::where('class_id', $class->id)
                    ->selectRaw('is_published, COUNT(*) as count')
                    ->groupBy('is_published')
                    ->get()
                    ->keyBy(fn ($item) => (int) $item->is_published);

                $class->total_exams = $stats->sum('count');
                $class->published_count = $stats->get(1)?->count ?? 0;
                $class->draft_count = $stats->get(0)?->count ?? 0;
            });
    }
}
