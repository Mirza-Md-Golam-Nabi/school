<?php

namespace App\Filament\Resources\SmsLogs;

use App\Enums\Permissions\SmsPermission;
use App\Enums\SmsEvent;
use App\Enums\SmsStatus;
use App\Filament\Concerns\HasSmsPermission;
use App\Filament\Resources\Concerns\ResponsiveText;
use App\Filament\Resources\SmsLogs\Pages\ListSmsLogs;
use App\Models\SmsLog;
use App\Models\SmsVendorResponseCode;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SmsLogResource extends Resource
{
    use HasSmsPermission;

    protected static ?string $model = SmsLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|UnitEnum|null $navigationGroup = 'SMS';

    protected static ?string $navigationLabel = 'SMS Logs';

    protected static ?int $navigationSort = 5;

    protected static function smsPermission(): SmsPermission
    {
        return SmsPermission::VIEW_SMS_LOGS;
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with('vendor:id,name,driver'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('recipient_name')
                    ->label('Recipient')
                    ->description(fn (SmsLog $record): string => $record->phone)
                    ->searchable(['recipient_name', 'phone'])
                    ->placeholder('—')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('event')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('message')
                    ->limit(50)
                    ->tooltip(fn (SmsLog $record): string => $record->message)
                    ->searchable()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('parts')
                    ->label('SMS')
                    ->numeric()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('cost')
                    ->numeric(2)
                    ->prefix('৳')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('status')
                    ->badge()
                    ->tooltip(fn (SmsLog $record): ?string => $record->error_message),
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('response_code')
                    ->label('Code')
                    ->badge()
                    ->color(fn (SmsLog $record): string => $record->status === SmsStatus::Sent ? 'success' : 'danger')
                    ->tooltip(fn (SmsLog $record): ?string => $record->vendor
                        ? SmsVendorResponseCode::lookup($record->vendor->driver, $record->response_code)?->message
                        : null)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sent_at')
                    ->dateTime('d M Y, h:i A')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SmsStatus::class),
                SelectFilter::make('event')
                    ->options(SmsEvent::class),
                Filter::make('date')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '<=', $date))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSmsLogs::route('/'),
        ];
    }
}
