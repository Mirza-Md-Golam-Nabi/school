<?php

namespace App\Filament\Resources\SmsBalanceTransactions;

use App\Enums\Permissions\SmsPermission;
use App\Enums\SmsBalanceTransactionType;
use App\Filament\Concerns\HasSmsPermission;
use App\Filament\Resources\Concerns\ResponsiveText;
use App\Filament\Resources\SmsBalanceTransactions\Pages\ListSmsBalanceTransactions;
use App\Models\SmsBalanceTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SmsBalanceTransactionResource extends Resource
{
    use HasSmsPermission;

    protected static ?string $model = SmsBalanceTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'SMS';

    protected static ?string $navigationLabel = 'Balance History';

    protected static ?int $navigationSort = 4;

    protected static function smsPermission(): SmsPermission
    {
        return SmsPermission::MANAGE_SMS_SETTINGS;
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return Response::deny();
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny();
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('amount')
                    ->numeric(2)
                    ->prefix('৳')
                    ->color(fn (SmsBalanceTransaction $record): string => (float) $record->amount < 0 ? 'danger' : 'success')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('sms_parts')
                    ->label('SMS')
                    ->numeric()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('balance_after')
                    ->label('Balance')
                    ->numeric(2)
                    ->prefix('৳')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('description')
                    ->wrap()
                    ->placeholder('—')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(SmsBalanceTransactionType::class),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSmsBalanceTransactions::route('/'),
        ];
    }
}
