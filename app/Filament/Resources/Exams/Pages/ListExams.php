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
            ->withCount('exams')
            ->orderBy('order')
            ->get();

        return compact('classes');
    }
}
