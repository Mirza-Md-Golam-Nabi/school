<?php

namespace App\Filament\Resources\LeaveApplications\Schemas;

use App\Actions\Leave\ResolveLeaveUsageAction;
use App\Enums\Gender;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\StaffProfile;
use App\Models\TeacherProfile;
use App\Services\WorkingDaysCalculator;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
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
                            ->live()
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

                        self::usageEntry(fn (Get $get, ?LeaveApplication $record): ?array => self::resolveUsage(
                            $get('applicant_type'),
                            $get('applicant_id'),
                            $get('leave_type_id'),
                            $get('from_date'),
                            (int) $get('total_days'),
                            $record,
                        )),

                        Textarea::make('reason')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * The yearly leave limit is a warning, never a block: this entry shows how
     * many days of the type were already taken this year and flags the limit
     * being passed. $resolveUsage returns null while the form lacks the inputs.
     */
    public static function usageEntry(Closure $resolveUsage): TextEntry
    {
        return TextEntry::make('leave_usage')
            ->label('Leave Used This Year')
            ->state(fn (TextEntry $component): ?string => ($usage = $component->evaluate($resolveUsage))
                ? ResolveLeaveUsageAction::summary($usage)
                : null)
            ->color(fn (TextEntry $component): string => ($component->evaluate($resolveUsage)['exceeds'] ?? false) ? 'danger' : 'success')
            ->icon(fn (TextEntry $component): string => ($component->evaluate($resolveUsage)['exceeds'] ?? false)
                ? 'heroicon-o-exclamation-triangle'
                : 'heroicon-o-check-circle')
            ->visible(fn (TextEntry $component): bool => $component->evaluate($resolveUsage) !== null)
            ->columnSpanFull();
    }

    /**
     * @return array{allowed: int, taken: int, after: int, exceeds: bool, excess: int}|null
     */
    public static function resolveUsage(
        ?string $applicantType,
        mixed $applicantId,
        mixed $leaveTypeId,
        mixed $fromDate,
        int $requestedDays,
        ?LeaveApplication $ignore = null,
    ): ?array {
        $leaveType = filled($leaveTypeId) ? LeaveType::find($leaveTypeId) : null;

        if (blank($applicantType) || blank($applicantId) || ! $leaveType || blank($fromDate)) {
            return null;
        }

        return app(ResolveLeaveUsageAction::class)->handle(
            $applicantType,
            (int) $applicantId,
            $leaveType,
            Carbon::parse($fromDate)->year,
            $requestedDays,
            $ignore?->id,
        );
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
