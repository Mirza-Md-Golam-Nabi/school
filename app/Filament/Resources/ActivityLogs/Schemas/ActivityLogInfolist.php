<?php

namespace App\Filament\Resources\ActivityLogs\Schemas;

use App\Enums\ActivityLogEvent;
use App\Models\ActivityLog;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ActivityLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('সাধারণ তথ্য')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('causer.name')
                            ->label('User')
                            ->default('System'),

                        TextEntry::make('event')
                            ->label('Event')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => ActivityLogEvent::tryFrom($state ?? '')?->getLabel() ?? ($state ?? '—'))
                            ->color(fn (?string $state): string => ActivityLogEvent::tryFrom($state ?? '')?->getColor() ?? 'gray'),

                        TextEntry::make('log_name')
                            ->label('Module')
                            ->badge(),

                        TextEntry::make('subject_type')
                            ->label('Subject Type')
                            ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'),

                        TextEntry::make('subject_id')
                            ->label('Subject ID')
                            ->default('—'),

                        TextEntry::make('created_at')
                            ->label('Time')
                            ->dateTime('d M Y, h:i:s A'),

                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                    ]),

                Section::make('Old Value')
                    ->columnSpan(1)
                    ->visible(fn (ActivityLog $record): bool => filled($record->properties->get('old')))
                    ->schema([
                        KeyValueEntry::make('changes_old')
                            ->hiddenLabel()
                            ->state(fn (ActivityLog $record): array => self::flattenForDisplay((array) $record->properties->get('old', []))),
                    ]),

                Section::make('New Value')
                    ->columnSpan(1)
                    ->visible(fn (ActivityLog $record): bool => filled($record->properties->get('attributes')))
                    ->schema([
                        KeyValueEntry::make('changes_new')
                            ->hiddenLabel()
                            ->state(fn (ActivityLog $record): array => self::flattenForDisplay((array) $record->properties->get('attributes', []))),
                    ]),

                Section::make('অতিরিক্ত তথ্য')
                    ->columnSpanFull()
                    ->visible(fn (ActivityLog $record): bool => $record->properties->except(['attributes', 'old'])->isNotEmpty())
                    ->schema([
                        KeyValueEntry::make('extra_properties')
                            ->hiddenLabel()
                            ->state(fn (ActivityLog $record): array => self::flattenForDisplay($record->properties->except(['attributes', 'old'])->toArray())),
                    ]),
            ]);
    }

    /**
     * KeyValueEntry only renders flat scalar values. Array-valued properties
     * (e.g. a multi-select FK like `class_id` or its `_label` counterpart)
     * would otherwise crash Filament's htmlspecialchars() call, so they're
     * flattened to a readable string here.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, string>
     */
    private static function flattenForDisplay(array $properties): array
    {
        return collect($properties)
            ->map(function (mixed $value): string {
                if (is_array($value)) {
                    $isFlat = collect($value)->every(fn (mixed $item): bool => is_scalar($item) || is_null($item));

                    return $isFlat
                        ? implode(', ', array_map(fn (mixed $item): string => $item === null ? '—' : (string) $item, $value))
                        : json_encode($value);
                }

                if (is_bool($value)) {
                    return $value ? 'Yes' : 'No';
                }

                return $value === null ? '—' : (string) $value;
            })
            ->all();
    }
}
