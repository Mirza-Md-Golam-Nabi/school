<?php

namespace App\Filament\Resources\Exams\Pages;

use App\Filament\Resources\Exams\ExamResource;
use App\Models\Classes;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\Page;

class ListExams extends Page
{
    protected static string $resource = ExamResource::class;

    protected string $view = 'filament.resources.exams.pages.list-exams';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getViewData(): array
    {
        $classes = Classes::active()
            ->withCount([
                'exams',
                'exams as published_exams_count' => fn ($query) => $query->where('is_published', true),
                'exams as pending_exams_count' => fn ($query) => $query->where('is_published', false),
            ])
            ->orderBy('order')
            ->get();

        return compact('classes');
    }
}
