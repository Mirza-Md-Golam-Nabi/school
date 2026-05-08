<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Models\Role;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Role')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions')
                    ->badge()
                    ->color('success'),

                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users')
                    ->badge()
                    ->color('info'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->before(function (Role $record) {
                        abort_if($record->name === 'super_admin', 403, 'Super Admin role cannot be deleted.');
                    }),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
