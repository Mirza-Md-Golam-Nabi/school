<?php

namespace App\Filament\Resources\Subjects\Pages;

use App\Actions\BuildSubjectImportTemplateAction;
use App\Actions\ImportSubjectsFromSpreadsheetAction;
use App\Enums\SubjectListColumn;
use App\Filament\Resources\Subjects\SubjectResource;
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

class ListSubjects extends ListRecords
{
    protected static string $resource = SubjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                $this->downloadSubjectImportTemplateAction(),
                $this->importSubjectsAction(),
            ])
                ->label('Import (Excel)')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('success')
                ->button()
                ->visible(fn (): bool => SubjectResource::canCreate()),
            $this->downloadSubjectListAction(),
            CreateAction::make(),
        ];
    }

    /**
     * সব subject-এর তালিকা PDF-এ — কোন কলাম থাকবে তা বেছে নেওয়া যায়, ডিফল্টে সব সিলেক্ট।
     */
    protected function downloadSubjectListAction(): Action
    {
        return Action::make('downloadSubjectList')
            ->label('Subject List (PDF)')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('info')
            ->schema([
                Section::make('Columns')
                    ->schema([
                        CheckboxList::make('columns')
                            ->hiddenLabel()
                            ->options(SubjectListColumn::options())
                            ->default(SubjectListColumn::defaults())
                            ->columns(['default' => 2, 'md' => 3])
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
            ->modalHeading('Download Subject List (PDF)')
            ->modalSubmitActionLabel('Download')
            ->action(fn (array $data) => $this->redirect(route('subject-list.download', [
                'columns' => $data['columns'] ?? [],
                'orientation' => $data['orientation'] ?? 'P',
            ])));
    }

    protected function downloadSubjectImportTemplateAction(): Action
    {
        return Action::make('downloadSubjectImportTemplate')
            ->label('Download Demo File')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->action(function (): StreamedResponse {
                $contents = app(BuildSubjectImportTemplateAction::class)->handle();

                return response()->streamDownload(
                    fn () => print ($contents),
                    'subject-import-demo.xlsx',
                    ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                );
            });
    }

    protected function importSubjectsAction(): Action
    {
        return Action::make('importSubjects')
            ->label('Upload Excel File')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading('Import Subjects from Excel')
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

                $result = app(ImportSubjectsFromSpreadsheetAction::class)->handle(
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
                    ->title("{$result['imported']}টা subject import হয়েছে")
                    ->body('এখন Class/Group-এ subject assign করতে পারবেন।')
                    ->send();
            });
    }
}
