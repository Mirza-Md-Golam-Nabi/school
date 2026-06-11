<?php

namespace App\Filament\Resources\LeaveApplications\Schemas;

use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use App\Services\WorkingDaysCalculator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class LeaveApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Applicant')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('applicant_type')
                                ->label('Applicant Type')
                                ->options([
                                    TeacherProfile::class => 'Teacher',
                                    StaffProfile::class => 'Staff',
                                ])
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('applicant_id', null)),

                            Select::make('applicant_id')
                                ->label('Applicant')
                                ->required()
                                ->options(function (Get $get) {
                                    $type = $get('applicant_type');
                                    if ($type === TeacherProfile::class) {
                                        return TeacherProfile::with('user')
                                            ->active()
                                            ->get()
                                            ->pluck('user.name', 'id');
                                    }
                                    if ($type === StaffProfile::class) {
                                        return StaffProfile::with('user')
                                            ->active()
                                            ->get()
                                            ->pluck('user.name', 'id');
                                    }

                                    return [];
                                })
                                ->searchable()
                                ->live(),
                        ]),
                    ]),

                Section::make('Leave Details')
                    ->schema([
                        Select::make('leave_type_id')
                            ->label('Leave Type')
                            ->relationship('leaveType', 'name', fn ($query) => $query->where('is_active', true))
                            ->required(),

                        Grid::make(3)->schema([
                            DatePicker::make('from_date')
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                    self::recalculateTotalDays($get, $set);
                                }),

                            DatePicker::make('to_date')
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                    self::recalculateTotalDays($get, $set);
                                }),

                            TextInput::make('total_days')
                                ->label('Total Working Days')
                                ->numeric()
                                ->required()
                                ->readOnly(),
                        ]),

                        Textarea::make('reason')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function recalculateTotalDays(Get $get, Set $set): void
    {
        $from = $get('from_date');
        $to = $get('to_date');

        if (! $from || ! $to) {
            return;
        }

        $fromDate = Carbon::parse($from);
        $toDate = Carbon::parse($to);

        if ($toDate->lt($fromDate)) {
            $set('total_days', 0);

            return;
        }

        $calculator = app(WorkingDaysCalculator::class);
        $set('total_days', $calculator->count($fromDate, $toDate));
    }
}
