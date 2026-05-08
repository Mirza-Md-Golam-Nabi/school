<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Enums\PermissionRegistry;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\GridDirection;
use Illuminate\Support\Facades\DB;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Role Information')
                ->icon('heroicon-o-identification')
                ->description('Give this role a unique name.')
                ->compact()
                ->schema([
                    TextInput::make('name')
                        ->label('Role Name')
                        ->placeholder('e.g. Admin, Teacher...')
                        ->required()
                        ->unique(table: 'roles', ignoreRecord: true)
                        ->maxLength(100)
                        ->live(debounce: 500)
                        ->prefixIcon('heroicon-o-shield-check')
                        ->prefixIconColor(fn ($get) => match ($get('__name_exists')) {
                            true => 'danger',
                            false => 'success',
                            default => null,
                        })
                        ->suffixIcon(fn ($get) => match ($get('__name_exists')) {
                            true => 'heroicon-o-x-circle',
                            false => 'heroicon-o-check-circle',
                            default => null,
                        })
                        ->suffixIconColor(fn ($get) => match ($get('__name_exists')) {
                            true => 'danger',
                            false => 'success',
                            default => null,
                        })
                        ->afterStateUpdated(function ($state, $set, $record, $livewire, $component) {
                            if (! filled($state)) {
                                $set('__name_exists', null);

                                return;
                            }

                            // শুধু এখানে একবারই DB hit হয়
                            $exists = DB::table('roles')
                                ->where('name', $state)
                                ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                ->exists();

                            $set('__name_exists', $exists);

                            $livewire->validateOnly($component->getStatePath());
                        })
                        ->columnSpanFull(),
                ]),

            Section::make('Assign Permissions')
                ->icon('heroicon-o-lock-open')
                ->description('Select what this role is allowed to do. Each section represents a module.')
                ->compact()
                ->schema(self::buildPermissionSections()),

        ])->columns(1);
    }

    private static function buildPermissionSections(): array
    {
        $output = [];
        $permissions = (new PermissionRegistry)();
        foreach ($permissions as $groupName => $cases) {
            $options = collect($cases)
                ->mapWithKeys(fn ($case) => [
                    $case->value => str($case->value)
                        ->replace('_', ' ')
                        ->title()
                        ->toString(),
                ])
                ->toArray();

            $output[] = Section::make($groupName)
                ->icon('heroicon-o-key')
                ->collapsible()
                ->compact()
                ->schema([
                    CheckboxList::make('permissions_'.str($groupName)->snake()->toString())
                        ->options($options)
                        ->columns(4)
                        ->gridDirection(GridDirection::Row)
                        ->bulkToggleable()
                        ->hiddenLabel(),
                ]);
        }

        return $output;
    }
}
