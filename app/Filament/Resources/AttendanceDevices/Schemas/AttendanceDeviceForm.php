<?php

namespace App\Filament\Resources\AttendanceDevices\Schemas;

use App\Enums\AttendanceDeviceDriver;
use App\Models\AttendanceDevice;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceDeviceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Device Information')
                    ->description('The API token is generated automatically and shown only once, right after the device is created.')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g. Main Gate K40'),

                        TextInput::make('serial_number')
                            ->label('Serial Number')
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->helperText('Optional — printed on the device label or shown in its System Info menu.'),

                        Select::make('driver')
                            ->options(AttendanceDeviceDriver::class)
                            ->default(AttendanceDeviceDriver::ZkPull)
                            ->required()
                            ->native(false),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false)
                            ->helperText('An inactive device is rejected by the API, so its sync client stops delivering punches.'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),

                Section::make('Last Report from the Device')
                    ->description('Sent by the laptop sync script after each sync.')
                    ->schema([
                        TextEntry::make('capacity_report')
                            ->label('Capacity')
                            ->state(fn (?AttendanceDevice $record): ?string => $record?->capacitySummary())
                            ->placeholder('No report yet.'),

                        TextEntry::make('unknown_users_report')
                            ->label('Users on the device that this software does not know')
                            ->helperText('They are left untouched. Remove them on the device, or link the enroll ID to a person.')
                            ->state(fn (?AttendanceDevice $record): array => collect($record?->unknown_device_users ?? [])
                                ->map(fn (array $user): string => trim("#{$user['enroll_id']} ".($user['name'] ?? '')))
                                ->all())
                            ->listWithLineBreaks()
                            ->placeholder('None'),
                    ])
                    ->visibleOn('edit'),
            ])
            ->columns(1);
    }
}
