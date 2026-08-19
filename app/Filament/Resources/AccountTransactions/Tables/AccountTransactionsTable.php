<?php

namespace App\Filament\Resources\AccountTransactions\Tables;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Filament\Resources\Concerns\ResponsiveText;
use App\Models\AccountTransaction;
use App\Models\SchoolAccount;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                TextColumn::make('party')
                    ->label('Party')
                    ->state(fn (AccountTransaction $record): ?string => $record->resolvePartyLabel())
                    ->description(fn (AccountTransaction $record): string => $record->partyRoleLabel())
                    ->placeholder('—')
                    ->wrap()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
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
                Filter::make('transaction_date')
                    ->label('Date Range')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('transaction_date', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('transaction_date', '<=', $date),
                            );
                    }),
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
                                TextEntry::make('party')
                                    ->label(fn (AccountTransaction $record): string => $record->partyRoleLabel())
                                    ->state(fn (AccountTransaction $record): ?string => $record->resolvePartyLabel())
                                    ->placeholder('—')
                                    ->icon('heroicon-o-user')
                                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
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
