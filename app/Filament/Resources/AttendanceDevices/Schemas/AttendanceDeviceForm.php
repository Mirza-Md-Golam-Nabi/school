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
                Section::make(__('Device Information'))
                    ->description(__('The API token is generated automatically and shown only once, right after the device is created.'))
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g. Main Gate K40'),

                        TextInput::make('serial_number')
                            ->label('Serial Number')
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->helperText(__('Optional — printed on the device label or shown in its System Info menu.')),

                        Select::make('driver')
                            ->options(AttendanceDeviceDriver::class)
                            ->default(AttendanceDeviceDriver::ZkPull)
                            ->required()
                            ->native(false),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false)
                            ->helperText(__('An inactive device is rejected by the API, so its sync client stops delivering punches.')),

                        TextInput::make('user_capacity')
                            ->label('Users Capacity')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->placeholder('e.g. :number', ['number' => '1000'])
                            ->helperText(__('Copy the limits from the device: Menu → System Info → Device Capacity. They are used to show how full the device is.')),

                        TextInput::make('fingerprint_capacity')
                            ->label('Fingerprint Capacity')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->placeholder('e.g. :number', ['number' => '3000']),

                        TextInput::make('card_capacity')
                            ->label('Card Capacity')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->placeholder('e.g. :number', ['number' => '3000']),

                        TextInput::make('record_capacity')
                            ->label('Attendance Record Capacity')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->placeholder('e.g. :number', ['number' => '100000']),

                        TextInput::make('log_retention_days')
                            ->label(__('Clear Device Log After (Days)'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(3650)
                            ->placeholder('e.g. 30')
                            ->helperText(__('Clears the whole device log once its oldest record is this many days old. All punches are uploaded to this software first. Leave empty to never clear.'))
                            ->columnSpanFull(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),

                Section::make(__('Last Report from the Device'))
                    ->description(__('Sent by the laptop sync script after each sync.'))
                    ->schema([
                        TextEntry::make('capacity_report')
                            ->label('Capacity')
                            ->state(fn (?AttendanceDevice $record): ?string => $record?->capacitySummary())
                            ->placeholder(__('No report yet.')),

                        TextEntry::make('log_cleared_at')
                            ->label(__('Device attendance log last cleared'))
                            ->state(fn (?AttendanceDevice $record): ?string => $record?->log_cleared_at?->format('d M Y, h:i A'))
                            ->placeholder(__('Never')),

                        TextEntry::make('unknown_users_report')
                            ->label(__('Users on the device that this software does not know'))
                            ->helperText(__('They are left untouched. Remove them on the device, or link the enroll ID to a person.'))
                            ->state(fn (?AttendanceDevice $record): array => collect($record?->unknown_device_users ?? [])
                                ->map(fn (array $user): string => trim("#{$user['enroll_id']} ".($user['name'] ?? '')))
                                ->all())
                            ->listWithLineBreaks()
                            ->placeholder(__('None')),
                    ])
                    ->visibleOn('edit'),
            ])
            ->columns(1);
    }
}
