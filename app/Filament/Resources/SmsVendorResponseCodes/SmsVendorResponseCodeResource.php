<?php

namespace App\Filament\Resources\SmsVendorResponseCodes;

use App\Enums\Permissions\SmsPermission;
use App\Enums\SmsVendorDriver;
use App\Filament\Concerns\HasSmsPermission;
use App\Filament\Resources\Concerns\ResponsiveText;
use App\Filament\Resources\SmsVendorResponseCodes\Pages\ManageSmsVendorResponseCodes;
use App\Models\SmsVendorResponseCode;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * What each SMS provider's API response codes mean. Everyone with SMS access
 * can read them; only the super-admin adds or changes them (e.g. when a new
 * provider is added).
 */
class SmsVendorResponseCodeResource extends Resource
{
    use HasSmsPermission;

    protected static ?string $model = SmsVendorResponseCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracketSquare;

    protected static string|UnitEnum|null $navigationGroup = 'SMS';

    protected static ?string $navigationLabel = 'Response Codes';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'code';

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
        return $schema
            ->components([
                Select::make('driver')
                    ->label('Provider')
                    ->options(SmsVendorDriver::class)
                    ->default(SmsVendorDriver::BulkSmsBd->value)
                    ->required()
                    ->native(false)
                    ->live(),
                TextInput::make('code')
                    ->required()
                    ->maxLength(20)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('driver', $get('driver') instanceof SmsVendorDriver ? $get('driver')->value : $get('driver')),
                    ),
                TextInput::make('message')
                    ->label('Meaning')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_success')
                    ->label('Means the SMS was accepted')
                    ->default(false),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Numeric order (202 before 1001), which a plain string sort would break.
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('driver')->orderByRaw('LENGTH(code)')->orderBy('code'))
            ->paginated([25, 50, 'all'])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('driver')
                    ->label('Provider')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('code')
                    ->searchable()
                    ->weight('bold')
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                TextColumn::make('message')
                    ->label('Meaning')
                    ->searchable()
                    ->wrap()
                    ->extraAttributes(['class' => ResponsiveText::CLASSES]),
                IconColumn::make('is_success')
                    ->label('Success')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('driver')
                    ->label('Provider')
                    ->options(SmsVendorDriver::class),
            ])
            ->recordActions([
                EditAction::make()->iconButton()->modalWidth('md'),
                DeleteAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSmsVendorResponseCodes::route('/'),
        ];
    }
}
