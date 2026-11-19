<?php

namespace App\Filament\Resources\SmsVendors;

use App\Enums\Permissions\SmsPermission;
use App\Filament\Concerns\HasSmsPermission;
use App\Filament\Resources\SmsVendors\Pages\CreateSmsVendor;
use App\Filament\Resources\SmsVendors\Pages\EditSmsVendor;
use App\Filament\Resources\SmsVendors\Pages\ListSmsVendors;
use App\Filament\Resources\SmsVendors\Schemas\SmsVendorForm;
use App\Filament\Resources\SmsVendors\Tables\SmsVendorsTable;
use App\Models\SmsVendor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Admins switch vendors on and off; only the super-admin adds vendors or
 * changes their API key and Sender ID.
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
        return static::superAdminOnly();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return static::superAdminOnly();
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return static::superAdminOnly();
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return static::superAdminOnly();
    }

    public static function form(Schema $schema): Schema
    {
        return SmsVendorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SmsVendorsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSmsVendors::route('/'),
            'create' => CreateSmsVendor::route('/create'),
            'edit' => EditSmsVendor::route('/{record}/edit'),
        ];
    }
}
