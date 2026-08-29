<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AdmitCards\AdmitCardResource;
use App\Filament\Resources\Classes\ClassesResource;
use App\Filament\Resources\Exams\ExamResource;
use App\Filament\Resources\Marksheets\MarksheetResource;
use Filament\Widgets\Widget;

class AcademicQuickActionsWidget extends Widget
{
    protected string $view = 'filament.widgets.academic-quick-actions';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = [
        'default' => 'full',
    ];

    protected static bool $isLazy = false;

    /**
     * @return array<int, array{label: string, icon: string, url: string, color: string}>
     */
    public function getViewData(): array
    {
        return [
            'actions' => [
                [
                    'label' => __('Create Exam'),
                    'icon' => 'heroicon-o-plus-circle',
                    'url' => ExamResource::getUrl('create'),
                    'color' => 'primary',
                ],
                [
                    'label' => __('Result Publish'),
                    'icon' => 'heroicon-o-check-badge',
                    'url' => ExamResource::getUrl('index'),
                    'color' => 'success',
                ],
                [
                    'label' => __('Marksheet'),
                    'icon' => 'heroicon-o-document-text',
                    'url' => MarksheetResource::getUrl('index'),
                    'color' => 'info',
                ],
                [
                    'label' => __('Admit Card'),
                    'icon' => 'heroicon-o-identification',
                    'url' => AdmitCardResource::getUrl('index'),
                    'color' => 'warning',
                ],
                [
                    'label' => __('Manage Class'),
                    'icon' => 'heroicon-o-building-library',
                    'url' => ClassesResource::getUrl('index'),
                    'color' => 'gray',
                ],
            ],
        ];
    }
}
