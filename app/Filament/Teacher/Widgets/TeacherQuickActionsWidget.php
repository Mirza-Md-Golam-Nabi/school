<?php

namespace App\Filament\Teacher\Widgets;

use App\Filament\Teacher\Pages\MySubjects;
use App\Filament\Teacher\Pages\StudentAttendance;
use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Filament\Teacher\Resources\FeePayments\FeePaymentResource;
use Filament\Widgets\Widget;

class TeacherQuickActionsWidget extends Widget
{
    protected string $view = 'filament.teacher.widgets.quick-actions';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = [
        'default' => 'full',
    ];

    protected static bool $isLazy = false;

    /**
     * @return array<int, array{label: string, icon: string, url: string, color: string}>
     */
    public function getViewData(): array
    {
        $actions = [];

        if (StudentAttendance::canAccess()) {
            $actions[] = [
                'label' => __('Take Attendance'),
                'icon' => 'heroicon-o-clipboard-document-check',
                'url' => StudentAttendance::getUrl(panel: 'teacher'),
                'color' => 'primary',
            ];
        }

        if (FeePaymentResource::canViewAny()) {
            $actions[] = [
                'label' => __('Fee Collection'),
                'icon' => 'heroicon-o-banknotes',
                'url' => FeePaymentResource::getUrl('index', panel: 'teacher'),
                'color' => 'success',
            ];
        }

        if (ExamResource::canViewAny()) {
            $actions[] = [
                'label' => __('Exam & Marks'),
                'icon' => 'heroicon-o-pencil-square',
                'url' => ExamResource::getUrl('index', panel: 'teacher'),
                'color' => 'info',
            ];
        }

        return ['actions' => $actions];
    }
}
