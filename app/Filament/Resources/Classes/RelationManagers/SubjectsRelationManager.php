<?php

namespace App\Filament\Resources\Classes\RelationManagers;

use App\Enums\SubjectType;
use App\Models\Group;
use App\Models\Subject;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'subjects';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('subject_id')
                    ->label('Subject')
                    ->options(Subject::active()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('subject_type')
                    ->label('Subject Type')
                    ->options(SubjectType::class)
                    ->required()
                    ->default(SubjectType::Compulsory),

                // শুধু has_group = true হলে group field দেখাবে
                Select::make('group_id')
                    ->label('Group')
                    ->placeholder('All Groups (সব গ্রুপের জন্য)')
                    ->options(function (RelationManager $livewire) {
                        $class = $livewire->getOwnerRecord();

                        if (! $class->has_group) {
                            return [];
                        }

                        return Group::query()
                            ->whereHas('classes', fn ($q) => $q->where('classes.id', $class->id))
                            ->pluck('name', 'id');
                    })
                    ->hidden(function (RelationManager $livewire) {
                        return ! $livewire->getOwnerRecord()->has_group;
                    })
                    ->nullable(),
            ])
            ->columns([1]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('code')
                    ->searchable(),
                IconColumn::make('has_mcq')
                    ->boolean(),
                IconColumn::make('has_written')
                    ->boolean(),
                IconColumn::make('has_practical')
                    ->boolean(),
                TextColumn::make('group_name')
                    ->label('Group')
                    ->state(function ($record) {
                        // $record এখানে 'Subject' মডেল, যার সাথে 'pivot' অবজেক্টটি আছে।
                        $pivot = $record->pivot;

                        // চেক করুন পিভোট ডাটা লোড হয়েছে কিনা
                        if (! $pivot) {
                            return '—';
                        }

                        // যদি group_id নাল হয়
                        if (is_null($pivot->group_id)) {
                            return match ($pivot->subject_type) {
                                SubjectType::Compulsory => 'General',
                                SubjectType::Optional => 'Optional (All Groups)',
                                default => 'General',
                            };
                        }

                        return $pivot->group?->name;
                    })
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'General' => 'danger',
                        'Optional (All Groups)' => 'warning',
                        default => 'info', // গ্রুপগুলোর নাম থাকলে নীল দেখাবে
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // TrashedFilter::make(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Assign Subject')
                    ->preloadRecordSelect()
                    ->multiple()
                    ->recordSelectOptionsQuery(fn ($query) => $query->active())
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect() // Subject List Dropdown
                            ->label('Subject Name')
                            ->hiddenLabel(false),

                        Select::make('subject_type')
                            ->label('Subject Type')
                            ->options(SubjectType::class)
                            ->required()
                            ->default(SubjectType::Compulsory),

                        Select::make('group_id')
                            ->label('Group')
                            ->placeholder('All Groups (সব গ্রুপের জন্য)')
                            ->options(function (RelationManager $livewire) {
                                $class = $livewire->getOwnerRecord();
                                if (! $class->has_group) {
                                    return [];
                                }

                                return Group::query()
                                    ->whereHas('classes', fn ($q) => $q->where('classes.id', $class->id))
                                    ->pluck('name', 'id');
                            })
                            ->hidden(fn (RelationManager $livewire) => ! $livewire->getOwnerRecord()->has_group)
                            ->nullable(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->modalWidth('md'),
                DetachAction::make()
                    ->label('Remove')
                    ->modalHeading(fn (Subject $record): string => "Remove  {$record->name}")
                    ->modalSubmitActionLabel('Remove'),
                // ForceDeleteAction::make(),
                // RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label('Remove'),
                    DeleteBulkAction::make(),
                    // ForceDeleteBulkAction::make(),
                    // RestoreBulkAction::make(),
                ]),
            ]);
        /*

            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]));
                */
    }
}
