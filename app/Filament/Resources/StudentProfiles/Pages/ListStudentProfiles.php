<?php

namespace App\Filament\Resources\StudentProfiles\Pages;

use App\Filament\Resources\StudentProfiles\StudentProfileResource;
use App\Models\Classes;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\Page;

class ListStudentProfiles extends Page
{
    protected static string $resource = StudentProfileResource::class;

    protected string $view = 'filament.resources.student-profiles.pages.list-student-profiles';

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
                'studentProfiles' => fn ($q) => $q->whereNull('deleted_at'),
            ])
            ->orderBy('order')
            ->get();

        return compact('classes');
    }
}
