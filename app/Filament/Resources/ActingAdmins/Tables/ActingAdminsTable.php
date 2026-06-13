<?php

namespace App\Filament\Resources\ActingAdmins\Tables;

use App\Models\ActingAdmin;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActingAdminsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->recordAction(ViewAction::class)
            ->columns([
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('assignedBy.name')
                    ->label('Assigned By')
                    ->searchable(),
                TextColumn::make('from_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('to_date')
                    ->date()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->modalHeading(fn (ActingAdmin $record): string => 'Acting Admin: '.$record->user->name)
                    ->schema([
                        Section::make()
                            ->schema([
                                Grid::make([
                                    'default' => 2,
                                ])->schema([
                                    TextEntry::make('user.name')
                                        ->label('Acting User'),
                                    TextEntry::make('assignedBy.name')
                                        ->label('Assigned By'),
                                    TextEntry::make('from_date')
                                        ->label('From Date')
                                        ->date(),
                                    TextEntry::make('to_date')
                                        ->label('To Date')
                                        ->date(),
                                    IconEntry::make('is_active')
                                        ->label('Active')
                                        ->boolean(),
                                    TextEntry::make('remarks')
                                        ->label('Remarks')
                                        ->columnSpanFull(),
                                ]),
                            ]),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                EditAction::make()
                    ->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
