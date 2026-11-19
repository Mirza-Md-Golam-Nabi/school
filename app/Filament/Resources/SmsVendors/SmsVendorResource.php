<?php

namespace App\Filament\Resources\SmsVendors;

use App\Enums\Permissions\SmsPermission;
use App\Filament\Concerns\HasSmsPermission;
use App\Filament\Resources\SmsVendors\Pages\ListSmsVendors;
use App\Filament\Resources\SmsVendors\Tables\SmsVendorsTable;
use App\Models\SmsVendor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Read-only list of the SMS vendors. They are set in the central app
 * (NextFusion) and pushed here, so no one — not even the super-admin — adds,
 * edits or switches them from this panel.
 */
class SmsVendorResource extends Resource
{
    use HasSmsPermission;

    protected static ?string $model = SmsVendor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = 'SMS';

    protected static ?string $navigationLabel = 'SMS Vendors';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function smsPermission(): SmsPermission
    {
        return SmsPermission::MANAGE_SMS_SETTINGS;
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return self::setCentrally();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return self::setCentrally();
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return self::setCentrally();
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return self::setCentrally();
    }

    private static function setCentrally(): Response
    {
        return Response::deny('SMS vendor শুধু NextFusion থেকে সেট করা যায়।');
    }

    public static function table(Table $table): Table
    {
        return SmsVendorsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSmsVendors::route('/'),
        ];
    }
}
