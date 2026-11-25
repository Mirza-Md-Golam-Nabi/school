<?php

namespace App\Filament\Resources\AdmitCards\Tables;

use App\Actions\QueueAdmitCardBundleAction;
use App\Jobs\GenerateAdmitCardPdfJob;
use App\Models\AdmitCard;
use App\Models\Exam;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdmitCardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['student.user', 'student.class', 'student.section', 'exam.examType']))
            ->columns([
                TextColumn::make('student.roll_no')
                    ->label('Roll')
                    ->searchable(),
                TextColumn::make('student.user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student.section.name')
                    ->label('Section'),
                TextColumn::make('exam.examType.name')
                    ->label('Exam')
                    ->description(fn (AdmitCard $record): string => (string) $record->exam?->session_year),
                IconColumn::make('is_generated')
                    ->label('Generated')
                    ->boolean(),
                TextColumn::make('file_generated_at')
                    ->label('Generated At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('exam_id')
                    ->label('Exam')
                    ->relationship('exam', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Exam $record): string => "{$record->examType?->name} — {$record->session_label}")
                    ->searchable(),
                TernaryFilter::make('is_generated'),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->tooltip('Download')
                    ->visible(fn (AdmitCard $record): bool => self::hasGeneratedFile($record))
                    ->action(fn (AdmitCard $record): StreamedResponse => Storage::disk('local')->download(
                        $record->file_path,
                        "admit-card-{$record->student_id}-{$record->exam_id}.pdf",
                    )),
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('view')
                        ->label('View PDF')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->visible(fn (AdmitCard $record): bool => self::hasGeneratedFile($record))
                        ->url(fn (AdmitCard $record): string => route('admit-cards.view', $record))
                        ->openUrlInNewTab(),
                    Action::make('regenerate')
                        ->label('Regenerate')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->requiresConfirmation()
                        ->color('warning')
                        ->action(function (AdmitCard $record) {
                            $record->update(['is_generated' => false]);
                            GenerateAdmitCardPdfJob::dispatch($record->id);
                            app(QueueAdmitCardBundleAction::class)->handle($record->exam);

                            Notification::make()
                                ->title('Regeneration queued.')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Whether the admit card's PDF has been generated and still exists on disk.
     */
    private static function hasGeneratedFile(AdmitCard $record): bool
    {
        return $record->is_generated
            && $record->file_path
            && Storage::disk('local')->exists($record->file_path);
    }
}
