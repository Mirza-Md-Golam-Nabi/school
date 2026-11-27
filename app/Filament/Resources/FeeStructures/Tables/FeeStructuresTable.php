<?php

namespace App\Filament\Resources\FeeStructures\Tables;

use App\Actions\GenerateOneTimeFeeInvoicesAction;
use App\Enums\ClassLevel;
use App\Models\Classes;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Support\AcademicSession;
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
                    ->label('Session')
                    ->formatStateUsing(fn (int $state, FeeStructure $record): string => $record->class ? $record->class->sessionLabel($state) : (string) $state)
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
                    ->options(fn () => Classes::pluck('name', 'id')),
                SelectFilter::make('fee_type_id')
                    ->label('Fee Type')
                    ->options(fn () => FeeType::pluck('name', 'id')),
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
                    ->modalDescription(fn (FeeStructure $record): string => "This will create a \"{$record->feeType->name}\" invoice for every active student in {$record->class->name} ({$record->class->sessionLabel($record->session_year)}). Students who already have this invoice will be skipped.")
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
     * ফিল্টারে চলতি সেশন(গুলো) থাকে, আর তার সাথে আগামী সেশন (যদি তার structure তৈরি
     * হয়ে থাকে) নয়তো গত সেশন। জানুয়ারি–জুনে কলেজের চলতি সেশন স্কুলের থেকে এক বছর
     * পিছিয়ে থাকে, তাই সব ক্লাসের পেজে দুটোই আসে। ক্লাসের পেজে শুধু সেই ক্লাসের
     * structure আর সেশন দেখে সিদ্ধান্ত হয়।
     *
     * @return array<int, string>
     */
    private static function sessionYearOptions(HasTable $livewire): array
    {
        $currentYears = self::currentSessionYears($livewire);
        $otherYear = in_array(max($currentYears) + 1, self::existingSessionYears($livewire), true)
            ? max($currentYears) + 1
            : min($currentYears) - 1;

        $years = array_unique([...$currentYears, $otherYear]);
        rsort($years);

        $class = self::pageClass($livewire);

        return collect($years)
            ->mapWithKeys(fn (int $year): array => [$year => $class ? $class->sessionLabel($year) : (string) $year])
            ->all();
    }

    /**
     * ডিফল্টে চলতি সেশন(গুলো) দেখায়, আর আগামী সেশনের structure তৈরি হয়ে থাকলে সেটাও
     * সাথে দেখায় — যাতে নতুন সেশনে কপি করার পর ফলাফল সাথে সাথে চোখে পড়ে। চলতি কোনো
     * সেশনেরই structure না থাকলে গত সেশনেরটা দেখায়, টেবিল যাতে খালি না আসে।
     *
     * @return array<int, int>
     */
    private static function defaultSessionYears(HasTable $livewire): array
    {
        $currentYears = self::currentSessionYears($livewire);
        $existingYears = self::existingSessionYears($livewire);

        if (in_array(max($currentYears) + 1, $existingYears, true)) {
            return [max($currentYears) + 1, ...$currentYears];
        }

        if (array_intersect($currentYears, $existingYears) === [] && in_array(min($currentYears) - 1, $existingYears, true)) {
            return [min($currentYears) - 1];
        }

        return $currentYears;
    }

    /**
     * ক্লাসের পেজে সেই ক্লাসের চলতি সেশন; সব ক্লাসের পেজে স্কুলের চলতি সেশন, আর কলেজ
     * ক্লাস থাকলে কলেজের চলতি সেশনও (নতুনটা আগে)।
     *
     * @return non-empty-array<int, int>
     */
    private static function currentSessionYears(HasTable $livewire): array
    {
        $class = self::pageClass($livewire);

        if ($class) {
            return [$class->currentSessionYear()];
        }

        $years = [AcademicSession::currentYearFor()];

        if (Classes::query()->where('level', ClassLevel::College)->exists()) {
            $years[] = AcademicSession::currentYearFor(ClassLevel::College);
        }

        $years = array_values(array_unique($years));
        rsort($years);

        return $years;
    }

    private static function pageClass(HasTable $livewire): ?Classes
    {
        $classId = $livewire->classId ?? null;

        return $classId ? Classes::find($classId) : null;
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
