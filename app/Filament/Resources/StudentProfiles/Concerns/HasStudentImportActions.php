<?php

namespace App\Filament\Resources\StudentProfiles\Concerns;

use App\Actions\BuildStudentImportTemplateAction;
use App\Actions\ImportStudentsFromSpreadsheetAction;
use App\Models\Classes;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait HasStudentImportActions
{
    /**
     * Excel দিয়ে একসাথে অনেক student যোগ করার দুটো ধাপ — ডেমো ফাইল ডাউনলোড, তারপর পূরণ
     * করা ফাইল আপলোড। $classId দিলে ফাইলে class কলাম খালি থাকা রো-গুলো সেই ক্লাসে যায়।
     */
    protected function studentImportActionGroup(?int $classId = null): ActionGroup
    {
        return ActionGroup::make([
            $this->downloadStudentImportTemplateAction($classId),
            $this->importStudentsAction($classId),
        ])
            ->label('Import (Excel)')
            ->icon(Heroicon::OutlinedTableCells)
            ->color('success')
            ->button();
    }

    protected function downloadStudentImportTemplateAction(?int $classId = null): Action
    {
        return Action::make('downloadStudentImportTemplate')
            ->label('Download Demo File')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->action(function () use ($classId): StreamedResponse {
                $contents = app(BuildStudentImportTemplateAction::class)->handle($classId ? Classes::find($classId) : null);

                return response()->streamDownload(
                    fn () => print ($contents),
                    'student-import-demo.xlsx',
                    ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                );
            });
    }

    protected function importStudentsAction(?int $classId = null): Action
    {
        return Action::make('importStudents')
            ->label('Upload Excel File')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading('Import Students from Excel')
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

                $result = app(ImportStudentsFromSpreadsheetAction::class)->handle(
                    $file instanceof TemporaryUploadedFile ? $file->getRealPath() : (string) $file,
                    $this->studentImportClassId(),
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
                    ->title("{$result['imported']} জন student import হয়েছে")
                    ->body('প্রত্যেকের login email তার প্রোফাইলে আছে; password: "password" — প্রথম লগইনে বদলাতে হবে।')
                    ->persistent()
                    ->send();
            });
    }

    /**
     * যে পেজে ক্লাস নির্দিষ্ট (StudentsByClass) সেখানে সেই ক্লাসের id; All Classes পেজে null।
     */
    protected function studentImportClassId(): ?int
    {
        return ($this->classId ?? 0) ?: null;
    }
}
