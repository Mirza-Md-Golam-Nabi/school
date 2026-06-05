<?php

namespace App\Filament\Resources\Notices\Schemas;

use App\Enums\NoticeTargetType;
use App\Models\Notice;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NoticeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Notice Details')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('target_type')
                            ->label('Audience')
                            ->badge(),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->state(fn (Notice $record): string => $record->isScheduled() ? 'Scheduled' : 'Published')
                            ->color(fn (Notice $record): string => $record->isScheduled() ? 'warning' : 'success'),

                        TextEntry::make('published_at')
                            ->label('Published At')
                            ->dateTime('d M Y, h:i A'),

                        TextEntry::make('title')
                            ->label('Title')
                            ->weight('bold')
                            ->size('lg')
                            ->columnSpanFull(),

                        TextEntry::make('body')
                            ->label('Content')
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Target Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('targets_summary')
                            ->label('Targets')
                            ->state(fn (Notice $record): string => self::buildTargetsSummary($record))
                            ->columnSpanFull(),
                    ]),

                Section::make('Delivery')
                    ->columns(3)
                    ->schema([
                        IconEntry::make('send_sms')
                            ->label('SMS Enabled')
                            ->boolean(),

                        TextEntry::make('reads_count')
                            ->label('Read Count')
                            ->state(fn (Notice $record): int => $record->reads()->count()),

                        TextEntry::make('createdBy.name')
                            ->label('Created By'),

                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime('d M Y, h:i A'),
                    ]),
            ]);
    }

    private static function buildTargetsSummary(Notice $record): string
    {
        return match ($record->target_type) {
            NoticeTargetType::All => 'Everyone (All Users)',
            NoticeTargetType::Students => 'All Students',
            NoticeTargetType::Teachers => 'All Teachers',
            NoticeTargetType::ByClass,
            NoticeTargetType::IndividualStudent,
            NoticeTargetType::IndividualTeacher => $record->targets()
                ->with('targetable')
                ->get()
                ->pluck('targetable.name')
                ->filter()
                ->implode(', ') ?: '—',
        };
    }
}
