<?php

namespace App\Filament\Resources\AdmitCards\Tables;

use App\Jobs\GenerateAdmitCardPdfJob;
use App\Models\AdmitCard;
use App\Models\Exam;
use Filament\Actions\Action;
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

class AdmitCardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['student.user', 'student.class', 'student.section', 'exam.examType']))
            ->columns([
                TextColumn::make('student.user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student.class.name')
                    ->label('Class'),
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
                    ->getOptionLabelFromRecordUsing(fn (Exam $record): string => "{$record->examType?->name} — {$record->session_year}")
                    ->searchable(),
                TernaryFilter::make('is_generated'),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->visible(fn (AdmitCard $record): bool => $record->is_generated && $record->file_path && Storage::disk('local')->exists($record->file_path))
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

                        Notification::make()
                            ->title('Regeneration queued.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
