<?php

namespace App\Filament\Resources\AdmitCards\Pages;

use App\Actions\AttachClassDocumentStatsAction;
use App\Actions\GenerateAdmitCardsForExamTypeAction;
use App\Filament\Resources\AdmitCards\AdmitCardResource;
use App\Filament\Resources\AdmitCards\Schemas\AdmitCardDesignRadio;
use App\Filament\Resources\AdmitCards\Schemas\AdmitCardLanguageRadios;
use App\Models\AdmitCard;
use App\Models\Classes;
use App\Models\ExamType;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class ListAdmitCards extends Page
{
    protected static string $resource = AdmitCardResource::class;

    protected string $view = 'filament.resources.admit-cards.pages.list-admit-cards';

    /**
     * Computed on every render instead of stored in a public property:
     * Livewire re-fetches a public Eloquent collection from its IDs on
     * each request, which would drop the attached card stats.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $classes = Classes::active()
            ->orderBy('order', 'asc')
            ->get();

        return [
            'classes' => app(AttachClassDocumentStatsAction::class)->handle($classes, AdmitCard::class, 'admitCardStats', 'total_admit_cards'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateAdmitCards')
                ->label('Generate Admit Cards')
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->color('success')
                ->schema([
                    Select::make('exam_type_id')
                        ->label('Exam')
                        ->options(fn () => ExamType::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->native(false)
                        ->required(),
                    Select::make('page_size')
                        ->label('Page Size')
                        ->options(['A4' => 'A4', 'A5' => 'A5'])
                        ->default('A4')
                        ->native(false)
                        ->required(),
                    AdmitCardLanguageRadios::make(),
                    ...AdmitCardDesignRadio::components(),
                ])
                ->modalWidth(Width::FiveExtraLarge)
                ->modalHeading('Generate Admit Cards')
                ->modalDescription('Admit cards will be generated for every class that has a published exam of this type.')
                ->modalSubmitActionLabel('Generate')
                ->action(function (array $data) {
                    $examType = ExamType::findOrFail($data['exam_type_id']);

                    $result = app(GenerateAdmitCardsForExamTypeAction::class)
                        ->handle(
                            $examType,
                            auth()->id(),
                            $data['page_size'],
                            $data['design'],
                            $data['routine_language'] ?? 'en',
                            $data['student_info_language'] ?? 'en',
                        );

                    Notification::make()
                        ->title('Admit card generation queued')
                        ->body("Created: {$result['created']} | Switched to this design/language: {$result['updated']} | Skipped (already exists): {$result['skipped']}")
                        ->success()
                        ->send();

                    if ($result['classes_without_routine'] !== []) {
                        Notification::make()
                            ->title('কিছু ক্লাসের রুটিন নেই')
                            ->body('এই ক্লাসগুলোর Exam Schedule ফাঁকা, তাই তাদের রুটিনসহ Admit Card বানানো হয়নি: '.implode(', ', $result['classes_without_routine']))
                            ->warning()
                            ->persistent()
                            ->send();
                    }

                    $this->redirect(AdmitCardResource::getUrl('index'));
                }),
        ];
    }
}
