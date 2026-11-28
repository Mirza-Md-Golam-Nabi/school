<?php

namespace App\Filament\Resources\ActingAdmins\Schemas;

use App\Filament\Resources\ActingAdmins\ActingAdminResource;
use App\Models\ActingAdmin;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ActingAdminForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Acting User')
                    ->options(fn (): array => self::assignableUsers())
                    ->in(fn (): array => array_keys(self::assignableUsers()))
                    ->getOptionLabelFromRecordUsing(fn (User $record) => "{$record->name} ({$record->user_type->getLabel()})")
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('level')
                    ->label('Acting As')
                    ->options(fn (): array => ActingAdminResource::assignableLevelOptions())
                    ->in(fn (): array => array_keys(ActingAdminResource::assignableLevelOptions()))
                    ->required(),

                DatePicker::make('from_date')
                    ->required(),

                DatePicker::make('to_date')
                    ->afterOrEqual('from_date')
                    ->helperText('Leave blank for no end date — stays active until manually removed or deactivated.'),

                Toggle::make('is_active')
                    ->required(),

                Textarea::make('remarks')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Active teachers and staff who may be made acting admin. Only the
     * super-admin may pick themselves; anyone else would be granting
     * themselves more power.
     *
     * @return array<int, string>
     */
    private static function assignableUsers(): array
    {
        $users = ActingAdmin::activeUser();

        if (! ActingAdminResource::isSuperAdmin()) {
            unset($users[auth()->id()]);
        }

        return $users;
    }
}
