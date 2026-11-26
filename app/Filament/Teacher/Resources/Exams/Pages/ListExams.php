<?php

namespace App\Filament\Teacher\Resources\Exams\Pages;

use App\Filament\Teacher\Concerns\ScopesToTaughtClasses;
use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Models\Classes;
use App\Models\Exam;
use Filament\Resources\Pages\Page;

class ListExams extends Page
{
    use ScopesToTaughtClasses;

    protected static string $resource = ExamResource::class;

    protected string $view = 'filament.teacher.resources.exams.pages.list-exams';

    /**
     * Computed on every render instead of stored in a public property:
     * Livewire re-fetches a public Eloquent collection from its IDs on each
     * request, which would drop the attached exam counts.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $classes = Classes::active()
            ->whereIn('id', static::currentTeacherTaughtClassIds())
            ->orderBy('order', 'asc')
            ->get();

        $countsByClass = Exam::query()
            ->whereIn('class_id', $classes->modelKeys())
            ->selectRaw('class_id, is_published, COUNT(*) as count')
            ->groupBy('class_id', 'is_published')
            ->get()
            ->groupBy('class_id');

        $classes->each(function (Classes $class) use ($countsByClass): void {
            $stats = $countsByClass->get($class->id, collect())
                ->keyBy(fn (Exam $row): int => (int) $row->is_published);

            $class->total_exams = $stats->sum('count');
            $class->published_count = $stats->get(1)?->count ?? 0;
            $class->draft_count = $stats->get(0)?->count ?? 0;
        });

        return ['classes' => $classes];
    }
}
