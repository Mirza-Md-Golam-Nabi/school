<?php

namespace App\Filament\Resources\StudentFeeDiscounts\Schemas;

use App\Enums\StudentStatus;
use App\Models\Classes;
use App\Models\FeeDiscount;
use App\Models\FeeType;
use App\Models\StudentProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class StudentFeeDiscountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Student & Discount')
                    ->columns(2)
                    ->schema([
                        Select::make('class_id_filter')
                            ->label('Class')
                            ->options(fn () => Classes::active()->orderBy('order')->pluck('name', 'id'))
                            ->default(fn () => request()->integer('class_id') ?: null)
                            ->live()
                            ->dehydrated(false)
                            ->native(false)
                            ->afterStateHydrated(function ($component, $record): void {
                                if ($record?->student) {
                                    $component->state($record->student->current_class_id);
                                }
                            })
                            ->afterStateUpdated(fn (Set $set) => $set('student_id', null)),

                        Select::make('student_id')
                            ->label('Student')
                            ->options(fn (Get $get) => StudentProfile::with('user')
                                ->where('status', StudentStatus::Active)
                                ->when(
                                    $get('class_id_filter'),
                                    fn ($q, $classId) => $q->where('current_class_id', $classId)
                                )
                                ->get()
                                ->mapWithKeys(fn ($s) => [$s->id => $s->user->name.' (Roll: '.$s->roll_no.')'])
                            )
                            ->searchable()
                            ->required()
                            ->native(false),

                        Select::make('fee_type_id')
                            ->label('Fee Type')
                            ->options(FeeType::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->native(false),

                        Select::make('discount_id')
                            ->label('Discount')
                            ->options(
                                FeeDiscount::all()
                                    ->mapWithKeys(fn ($d) => [$d->id => $d->name.' ('.$d->discount_type->getLabel().': '.$d->discount_value.')'])
                            )
                            ->searchable()
                            ->required()
                            ->native(false)
                            ->columnSpanFull(),
                    ]),

                Section::make('Approval & Remarks')
                    ->columns(2)
                    ->schema([
                        TextInput::make('session_year')
                            ->label('Session Year')
                            ->required()
                            ->numeric()
                            ->minValue(2000)
                            ->maxValue(2100)
                            ->default(now()->year),
                        Select::make('approved_by')
                            ->label('Approved By')
                            ->relationship('approvedBy', 'name')
                            ->searchable()
                            ->nullable()
                            ->native(false),
                        Textarea::make('remarks')
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
