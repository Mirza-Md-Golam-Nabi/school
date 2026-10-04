<?php

namespace App\Filament\Resources\FeeStructures\Tables;

use App\Actions\GenerateOneTimeFeeInvoicesAction;
use App\Models\Classes;
use App\Models\FeeStructure;
use App\Models\FeeType;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FeeStructuresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('session_year', 'desc')
            ->columns([
                TextColumn::make('class.name')
                    ->label('Class')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('info'),
                TextColumn::make('feeType.name')
                    ->label('Fee Type')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('BDT')
                    ->sortable()
                    ->alignEnd()
                    ->weight('semibold'),
                TextColumn::make('due_day')
                    ->label('Due Day')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : "{$state}th")
                    ->alignCenter(),
                TextColumn::make('session_year')
                    ->label('Year')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('class_id')
                    ->label('Class')
                    ->options(Classes::pluck('name', 'id')),
                SelectFilter::make('fee_type_id')
                    ->label('Fee Type')
                    ->options(FeeType::pluck('name', 'id')),
                SelectFilter::make('session_year')
                    ->label('Session Year')
                    ->multiple()
                    ->options(fn (HasTable $livewire): array => self::sessionYearOptions($livewire))
                    ->default(fn (HasTable $livewire): array => self::defaultSessionYears($livewire)),
                TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->recordActions([
                Action::make('generateInvoices')
                    ->label('Generate Invoices')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Generate one-time invoices for every student in this class')
                    ->visible(fn (FeeStructure $record): bool => $record->is_active && ! $record->feeType?->is_monthly)
                    ->requiresConfirmation()
                    ->modalHeading('Generate One-Time Invoices')
                    ->modalDescription(fn (FeeStructure $record): string => "This will create a \"{$record->feeType->name}\" invoice for every active student in {$record->class->name} ({$record->session_year}). Students who already have this invoice will be skipped.")
                    ->modalSubmitActionLabel('Generate')
                    ->action(function (FeeStructure $record) {
                        $result = app(GenerateOneTimeFeeInvoicesAction::class)->handle($record);

                        Notification::make()
                            ->title('Invoices generated')
                            ->body("Generated: {$result['generated']} | Skipped (already exists): {$result['skipped']}")
                            ->success()
                            ->send();
                    }),
                EditAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * ফিল্টারে সবসময় দুটো বছর থাকে — বর্তমান বছর, আর তার সাথে আগামী বছর (যদি তার
     * structure তৈরি হয়ে থাকে) নয়তো গত বছর। ক্লাসের পেজে শুধু সেই ক্লাসের structure
     * দেখে সিদ্ধান্ত হয়।
     *
     * @return array<int, int>
     */
    private static function sessionYearOptions(HasTable $livewire): array
    {
        $currentYear = now()->year;
        $otherYear = in_array($currentYear + 1, self::existingSessionYears($livewire), true)
            ? $currentYear + 1
            : $currentYear - 1;

        $years = [$currentYear, $otherYear];
        rsort($years);

        return array_combine($years, $years);
    }

    /**
     * ডিফল্টে বর্তমান বছর দেখায়, আর আগামী বছরের structure তৈরি হয়ে থাকলে সেটাও সাথে
     * দেখায় — যাতে নতুন সেশনে কপি করার পর ফলাফল সাথে সাথে চোখে পড়ে। দুটোর কোনোটারই
     * structure না থাকলে গত বছরেরটা দেখায়, টেবিল যাতে খালি না আসে।
     *
     * @return array<int, int>
     */
    private static function defaultSessionYears(HasTable $livewire): array
    {
        $currentYear = now()->year;
        $existingYears = self::existingSessionYears($livewire);

        if (in_array($currentYear + 1, $existingYears, true)) {
            return [$currentYear + 1, $currentYear];
        }

        if (! in_array($currentYear, $existingYears, true) && in_array($currentYear - 1, $existingYears, true)) {
            return [$currentYear - 1];
        }

        return [$currentYear];
    }

    /**
     * @return array<int, int>
     */
    private static function existingSessionYears(HasTable $livewire): array
    {
        $classId = $livewire->classId ?? null;

        return FeeStructure::query()
            ->when($classId, fn ($query) => $query->where('class_id', $classId))
            ->distinct()
            ->pluck('session_year')
            ->map(fn (int|string $year): int => (int) $year)
            ->all();
    }
}
