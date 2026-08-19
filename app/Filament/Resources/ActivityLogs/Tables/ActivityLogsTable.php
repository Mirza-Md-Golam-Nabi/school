<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Enums\ActivityLogEvent;
use App\Enums\UserType;
use App\Models\ActivityLog;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->latest('id'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),

                TextColumn::make('causer.name')
                    ->label('User')
                    ->default('System')
                    ->searchable()
                    ->icon(Heroicon::OutlinedUser),

                TextColumn::make('log_name')
                    ->label('Module')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? ucwords(str_replace('_', ' ', $state)) : '—')
                    ->sortable(),

                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ActivityLogEvent::tryFrom($state ?? '')?->getLabel() ?? ($state ?? '—'))
                    ->color(fn (?string $state): string => ActivityLogEvent::tryFrom($state ?? '')?->getColor() ?? 'gray'),

                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(60)
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Module')
                    ->options(fn (): array => ActivityLog::query()
                        ->whereNotNull('log_name')
                        ->distinct()
                        ->orderBy('log_name')
                        ->pluck('log_name', 'log_name')
                        ->map(fn (string $name): string => ucwords(str_replace('_', ' ', $name)))
                        ->toArray()),

                SelectFilter::make('event')
                    ->label('Event')
                    ->options(ActivityLogEvent::class),

                SelectFilter::make('causer_id')
                    ->label('User')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => static::causerOptionsQuery()
                        ->where('name', 'like', "%{$search}%")
                        ->limit(50)
                        ->pluck('name', 'id')
                        ->toArray())
                    ->getOptionLabelUsing(fn ($value): ?string => User::query()->whereKey($value)->value('name'))
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $causerId) => $q->where('causer_id', $causerId)->where('causer_type', User::class),
                    )),

                Filter::make('created_at')
                    ->schema([
                        Grid::make(2)->schema([
                            DatePicker::make('from')->label('শুরুর তারিখ'),
                            DatePicker::make('until')->label('শেষের তারিখ'),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton(),
            ])
            ->toolbarActions([
                //
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function causerOptionsQuery(): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('user_type', [
                UserType::SuperAdmin,
                UserType::Admin,
                UserType::Teacher,
                UserType::Staff,
            ])
            ->orderBy('name');
    }
}
