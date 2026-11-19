<?php

namespace App\Filament\Resources\SmsRechargeRequests;

use App\Enums\Permissions\SmsPermission;
use App\Filament\Concerns\HasSmsPermission;
use App\Filament\Resources\SmsRechargeRequests\Pages\ListSmsRechargeRequests;
use App\Filament\Resources\SmsRechargeRequests\Tables\SmsRechargeRequestsTable;
use App\Models\SmsRechargeRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SmsRechargeRequestResource extends Resource
{
    use HasSmsPermission;

    protected static ?string $model = SmsRechargeRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'SMS';

    protected static ?string $navigationLabel = 'Recharge Requests';

    protected static ?int $navigationSort = 3;

    protected static function smsPermission(): SmsPermission
    {
        return SmsPermission::MANAGE_SMS_SETTINGS;
    }

    /**
     * Requests are made through the list page's "New Recharge Request" action,
     * and are completed only from outside (the payment confirmation).
     */
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
        return SmsRechargeRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSmsRechargeRequests::route('/'),
        ];
    }
}
