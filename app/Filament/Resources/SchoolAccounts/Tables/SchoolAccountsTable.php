<?php

namespace App\Filament\Resources\SchoolAccounts\Tables;

use App\Actions\SyncFeeTypeAccountTransactionsAction;
use App\Filament\Resources\SchoolAccounts\Schemas\FeeTypeResyncPolicyFields;
use App\Models\FeeType;
use App\Models\SchoolAccount;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SchoolAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('feeTypes'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->wrap()
                    ->extraAttributes(['class' => FeeTypeResyncPolicyFields::RESPONSIVE_TEXT]),
                TextColumn::make('current_balance')
                    ->label('Current Balance')
                    ->money('BDT')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success')
                    ->extraAttributes(['class' => FeeTypeResyncPolicyFields::RESPONSIVE_TEXT]),
                TextColumn::make('feeTypes.name')
                    ->label('Fee Types')
                    ->badge()
                    ->color('gray')
                    ->placeholder('None assigned')
                    ->wrap()
                    ->extraAttributes(['class' => FeeTypeResyncPolicyFields::RESPONSIVE_TEXT]),
                TextColumn::make('transactions_count')
                    ->label('Transactions')
                    ->counts('transactions')
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->toggleable()
                    ->extraAttributes(['class' => FeeTypeResyncPolicyFields::RESPONSIVE_TEXT]),
                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->extraAttributes(['class' => FeeTypeResyncPolicyFields::RESPONSIVE_TEXT]),
            ])
            ->filters([])
            ->recordActions([
                static::addFeeTypeAction(),
                EditAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function addFeeTypeAction(): Action
    {
        return Action::make('addFeeType')
            ->label('')
            ->tooltip('Add Fee Type')
            ->icon(Heroicon::Plus)
            ->iconButton()
            ->modalHeading('Add Fee Type')
            ->modalSubmitActionLabel('Add')
            ->schema(fn (SchoolAccount $record) => [
                Radio::make('scope_filter')
                    ->label('Show')
                    ->options([
                        'unassigned' => 'Unassigned only (শুধু অনির্ধারিত)',
                        'all' => 'All fee types (সব ফি টাইপ)',
                    ])
                    ->default('unassigned')
                    ->inline()
                    ->live()
                    ->dehydrated(false)
                    ->extraAttributes(['class' => FeeTypeResyncPolicyFields::RESPONSIVE_TEXT]),

                Select::make('fee_type_id')
                    ->label('Fee Type')
                    ->required()
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->extraAttributes(['class' => FeeTypeResyncPolicyFields::RESPONSIVE_TEXT])
                    ->options(function (Get $get) use ($record) {
                        $unassigned = FeeType::whereNull('school_account_id')
                            ->orderBy('name')
                            ->pluck('name', 'id');

                        if ($get('scope_filter') !== 'all') {
                            return $unassigned->toArray();
                        }

                        $assigned = FeeType::whereNotNull('school_account_id')
                            ->where('school_account_id', '!=', $record->id)
                            ->with('schoolAccount')
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (FeeType $feeType) => [
                                $feeType->id => "{$feeType->name} — {$feeType->schoolAccount->name}",
                            ]);

                        return [
                            'Unassigned (অনির্ধারিত)' => $unassigned->toArray(),
                            'Assigned (নির্ধারিত)' => $assigned->toArray(),
                        ];
                    }),

                View::make('filament.resources.school-accounts.fee-type-assignment-summary')
                    ->viewData(function (Get $get) {
                        $feeType = ($feeTypeId = $get('fee_type_id'))
                            ? FeeType::with('schoolAccount')->find($feeTypeId)
                            : null;

                        $resyncMode = $get('resync_mode');
                        $resyncYears = array_filter((array) ($get('resync_years') ?? []));

                        return [
                            'feeType' => $feeType,
                            'resyncMode' => $resyncMode,
                            'moveAmount' => match (true) {
                                ! $feeType => null,
                                $resyncMode === 'all' => $feeType->postedAmount(),
                                $resyncMode === 'years' => $feeType->postedAmount($resyncYears),
                                default => null,
                            },
                        ];
                    })
                    ->visible(fn (Get $get) => filled($get('fee_type_id'))),

                ...FeeTypeResyncPolicyFields::make('fee_type_id'),
            ])
            ->action(function (array $data, SchoolAccount $record) {
                $feeType = FeeType::findOrFail($data['fee_type_id']);
                $feeType->update(['school_account_id' => $record->id]);

                app(SyncFeeTypeAccountTransactionsAction::class)
                    ->handleFromPolicy($feeType, $data['resync_mode'] ?? 'none', $data['resync_years'] ?? []);
            });
    }
}
