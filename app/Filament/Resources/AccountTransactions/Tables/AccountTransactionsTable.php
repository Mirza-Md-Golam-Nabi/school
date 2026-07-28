<?php

namespace App\Filament\Resources\AccountTransactions\Tables;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Filament\Resources\Concerns\ResponsiveText;
use App\Models\SchoolAccount;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AccountTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('transaction_date', 'desc')
            ->recordUrl(null)
            ->recordAction(ViewAction::class)
            ->columns([
                TextColumn::make('transaction_date')
                    ->date()
                    ->sortable()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('account.name')
                    ->label('Account')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('transaction_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('source_type')
                    ->label('Source')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('description')
                    ->searchable()
                    ->wrap()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('amount')
                    ->money('BDT')
                    ->alignEnd()
                    ->weight('semibold')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
            ])
            ->filters([
                SelectFilter::make('account_id')
                    ->label('Account')
                    ->options(fn () => SchoolAccount::pluck('name', 'id')),
                SelectFilter::make('transaction_type')
                    ->label('Type')
                    ->options(TransactionType::class),
                SelectFilter::make('source_type')
                    ->label('Source')
                    ->options(TransactionSource::class),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->modalHeading('Transaction Details')
                    ->schema([
                        Section::make('Details')
                            ->schema([
                                TextEntry::make('account.name')->label('Account')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                TextEntry::make('transaction_type')->label('Type')->badge(),
                                TextEntry::make('source_type')->label('Source')->badge()->color('gray'),
                                TextEntry::make('amount')->money('BDT')->weight('bold')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                TextEntry::make('transaction_date')->label('Date')->date()->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                TextEntry::make('description')->columnSpanFull()->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                TextEntry::make('createdBy.name')->label('Created By')->placeholder('—')->extraAttributes(['class' => ResponsiveText::CLASSES]),
                                TextEntry::make('created_at')->label('Created At')->dateTime()->extraAttributes(['class' => ResponsiveText::CLASSES]),
                            ])
                            ->columns(['default' => 2, 'sm' => 3]),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }
}
