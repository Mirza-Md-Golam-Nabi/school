<?php

namespace App\Filament\Resources\LeaveApplications\Schemas;

use App\Enums\Gender;
use App\Models\LeaveType;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use App\Services\WorkingDaysCalculator;
use Filament\Forms\Components\DatePicker;
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
                                ->afterStateUpdated(function (Set $set) {
                                    $set('applicant_id', null);
                                    $set('leave_type_id', null);
                                }),

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
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('leave_type_id', null)),
                        ]),
                    ]),

                Section::make('Leave Details')
                    ->schema([
                        Select::make('leave_type_id')
                            ->label('Leave Type')
                            ->options(function (Get $get) {
                                $gender = self::resolveApplicantGender($get('applicant_type'), $get('applicant_id'));

                                $query = LeaveType::query()->active();

                                if ($gender) {
                                    $query->applicableTo($gender);
                                }

                                return $query->pluck('name', 'id');
                            })
                            ->helperText('শুধু আবেদনকারীর জন্য বরাদ্দকৃত ছুটির ধরন এখানে দেখাবে।')
                            ->disabled(fn (Get $get) => blank($get('applicant_id')))
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

    private static function resolveApplicantGender(?string $applicantType, mixed $applicantId): ?Gender
    {
        if (blank($applicantType) || blank($applicantId)) {
            return null;
        }

        if ($applicantType === TeacherProfile::class) {
            return TeacherProfile::find($applicantId)?->gender;
        }

        if ($applicantType === StaffProfile::class) {
            return StaffProfile::find($applicantId)?->gender;
        }

        return null;
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
