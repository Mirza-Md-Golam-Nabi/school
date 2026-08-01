<?php

namespace App\Filament\Resources\FundTransactions\Tables;

use App\Enums\TransactionType;
use App\Filament\Resources\Concerns\ResponsiveText;
use App\Models\FundTransaction;
use App\Models\SchoolAccount;
use App\Models\TransactionCategory;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FundTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('transaction_date', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->weight('semibold')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('amount')
                    ->money('BDT')
                    ->alignEnd()
                    ->weight('semibold')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('schoolAccount.name')
                    ->label('Account')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('party_name')
                    ->label('Vendor / Donor')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('transaction_date')
                    ->date()
                    ->sortable()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(TransactionType::class),
                SelectFilter::make('transaction_category_id')
                    ->label('Category')
                    ->options(fn () => TransactionCategory::pluck('name', 'id')),
                SelectFilter::make('school_account_id')
                    ->label('Account')
                    ->options(fn () => SchoolAccount::pluck('name', 'id')),
            ])
            ->recordActions([
                Action::make('viewAttachment')
                    ->label('Attachment')
                    ->icon('heroicon-o-paper-clip')
                    ->iconButton()
                    ->visible(fn (FundTransaction $record): bool => filled($record->attachment_path))
                    ->url(fn (FundTransaction $record): string => route('fund-transactions.attachment', $record))
                    ->openUrlInNewTab(),
                EditAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
