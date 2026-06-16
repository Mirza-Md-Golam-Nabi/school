<?php

namespace App\Filament\Teacher\Resources\Exams;

use App\Filament\Resources\Exams\RelationManagers\SubjectConfigsRelationManager;
use App\Filament\Teacher\Resources\Exams\Pages\ListExams;
use App\Filament\Teacher\Resources\Exams\Pages\ViewExam;
use App\Models\Exam;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ExamResource extends Resource
{
    protected static ?string $model = Exam::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make()->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('examType.name')
                            ->label('Exam Type'),

                        TextEntry::make('class.name')
                            ->label('Class'),

                        TextEntry::make('session_year')
                            ->label('Session Year'),

                        TextEntry::make('is_published')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Published' : 'Draft')
                            ->color(fn (bool $state): string => $state ? 'success' : 'warning'),

                        TextEntry::make('start_date')
                            ->label('Start Date')
                            ->date(),

                        TextEntry::make('end_date')
                            ->label('End Date')
                            ->date(),
                    ]),
                ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('examType.name')
                    ->label('Exam Type')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('class.name')
                    ->label('Class')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('session_year')
                    ->label('Session')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('End Date')
                    ->date()
                    ->sortable(),

                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean(),
            ])
            ->recordUrl(fn (Exam $record): string => static::getUrl('view', ['record' => $record]))
            ->defaultSort('start_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            SubjectConfigsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExams::route('/'),
            'view' => ViewExam::route('/{record}'),
        ];
    }
}
