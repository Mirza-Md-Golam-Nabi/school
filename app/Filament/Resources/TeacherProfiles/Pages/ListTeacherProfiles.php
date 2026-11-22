<?php

namespace App\Filament\Resources\TeacherProfiles\Pages;

use App\Actions\BuildTeacherImportTemplateAction;
use App\Actions\ImportTeachersFromSpreadsheetAction;
use App\Enums\TeacherListColumn;
use App\Filament\Resources\TeacherProfiles\TeacherProfileResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\GridDirection;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListTeacherProfiles extends ListRecords
{
    protected static string $resource = TeacherProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                $this->downloadTeacherImportTemplateAction(),
                $this->importTeachersAction(),
            ])
                ->label('Import (Excel)')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('success')
                ->button()
                ->visible(fn (): bool => TeacherProfileResource::canCreate()),
            $this->downloadTeacherListAction(),
            CreateAction::make(),
        ];
    }

    protected function downloadTeacherListAction(): Action
    {
        return Action::make('downloadTeacherList')
            ->label('Teacher List (PDF)')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('info')
            ->schema([
                Section::make('Columns')
                    ->schema([
                        CheckboxList::make('columns')
                            ->hiddenLabel()
                            ->options(TeacherListColumn::options())
                            ->default(TeacherListColumn::defaults())
                            ->columns(['default' => 2, 'md' => 3, 'lg' => 4])
                            ->gridDirection(GridDirection::Row)
                            ->bulkToggleable(),
                    ]),
                Section::make('Orientation')
                    ->schema([
                        Radio::make('orientation')
                            ->hiddenLabel()
                            ->options([
                                'P' => 'Portrait',
                                'L' => 'Landscape',
                            ])
                            ->default('P')
                            ->inline()
                            ->inlineLabel(false)
                            ->required(),
                    ]),
            ])
            ->modalHeading('Download Teacher List (PDF)')
            ->modalSubmitActionLabel('Download')
            ->action(fn (array $data) => $this->redirect(route('teacher-list.download', [
                'columns' => $data['columns'] ?? [],
                'orientation' => $data['orientation'] ?? 'P',
            ])));
    }

    protected function downloadTeacherImportTemplateAction(): Action
    {
        return Action::make('downloadTeacherImportTemplate')
            ->label('Download Demo File')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->action(function (): StreamedResponse {
                $contents = app(BuildTeacherImportTemplateAction::class)->handle();

                return response()->streamDownload(
                    fn () => print ($contents),
                    'teacher-import-demo.xlsx',
                    ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                );
            });
    }

    protected function importTeachersAction(): Action
    {
        return Action::make('importTeachers')
            ->label('Upload Excel File')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading('Import Teachers from Excel')
            ->modalDescription('ডেমো ফাইল ডাউনলোড করে পূরণ করুন, তারপর এখানে আপলোড করুন। ফাইলের কোনো রো-তে ভুল থাকলে কিছুই import হবে না — ভুলগুলো দেখানো হবে।')
            ->modalSubmitActionLabel('Import')
            ->schema([
                FileUpload::make('file')
                    ->label('Excel File (.xlsx)')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->maxSize(5120)
                    ->storeFiles(false)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $file = $data['file'];

                $result = app(ImportTeachersFromSpreadsheetAction::class)->handle(
                    $file instanceof TemporaryUploadedFile ? $file->getRealPath() : (string) $file,
                );

                if ($result['errors'] !== []) {
                    $shown = array_slice($result['errors'], 0, 15);
                    $remaining = count($result['errors']) - count($shown);

                    Notification::make()
                        ->danger()
                        ->title('Import হয়নি — ফাইলে '.count($result['errors']).'টা ভুল আছে')
                        ->body(implode('<br>', array_map('e', $shown)).($remaining > 0 ? "<br>...আরও {$remaining}টা" : ''))
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title("{$result['imported']} জন teacher import হয়েছে")
                    ->body('প্রত্যেকে নিজের email দিয়ে login করবে; password: "'.ImportTeachersFromSpreadsheetAction::DEFAULT_PASSWORD.'"।')
                    ->persistent()
                    ->send();
            });
    }
}
