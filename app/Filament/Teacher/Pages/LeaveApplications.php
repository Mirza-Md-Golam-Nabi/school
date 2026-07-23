<?php

namespace App\Filament\Teacher\Pages;

use App\Enums\LeaveApplicationStatus;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\TeacherProfile;
use App\Services\WorkingDaysCalculator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

class LeaveApplications extends Page
{
    protected string $view = 'filament.teacher.pages.leave-applications';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentPlus;

    protected static string|UnitEnum|null $navigationGroup = 'Leave';

    protected static ?string $navigationLabel = 'Leave Applications';

    protected static ?int $navigationSort = 5;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('apply')
                ->label('Apply for Leave')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->schema([
                    Select::make('leave_type_id')
                        ->label('Leave Type')
                        ->options(fn (): Collection => $this->availableLeaveTypes()->pluck('name', 'id'))
                        ->helperText('অবশিষ্ট quota "My Leave" পেজে দেখুন।')
                        ->required(),

                    Grid::make(2)->schema([
                        DatePicker::make('from_date')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateTotalDays($get, $set)),

                        DatePicker::make('to_date')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateTotalDays($get, $set)),
                    ]),

                    TextInput::make('total_days')
                        ->label('Total Working Days')
                        ->numeric()
                        ->required()
                        ->readOnly(),

                    Textarea::make('reason')
                        ->required()
                        ->rows(3),
                ])
                ->action(fn (array $data) => $this->applyForLeave($data))
                ->modalHeading('Apply for Leave'),
        ];
    }

    public function applyForLeave(array $data): void
    {
        $profile = $this->resolveProfile();

        if (! $profile) {
            return;
        }

        if (! $this->availableLeaveTypes()->contains('id', $data['leave_type_id'])) {
            Notification::make()
                ->danger()
                ->title('এই ছুটির ধরনের জন্য আবেদন করার অনুমতি নেই।')
                ->send();

            return;
        }

        LeaveApplication::create([
            'applicant_type' => TeacherProfile::class,
            'applicant_id' => $profile->id,
            'leave_type_id' => $data['leave_type_id'],
            'from_date' => $data['from_date'],
            'to_date' => $data['to_date'],
            'total_days' => $data['total_days'],
            'reason' => $data['reason'],
            'status' => LeaveApplicationStatus::Pending,
            'applied_by' => auth()->id(),
        ]);

        Notification::make()
            ->success()
            ->title('Leave application submitted')
            ->send();
    }

    public function cancel(int $applicationId): void
    {
        $profile = $this->resolveProfile();

        $application = LeaveApplication::query()
            ->where('id', $applicationId)
            ->where('applicant_type', TeacherProfile::class)
            ->where('applicant_id', $profile?->id)
            ->where('status', LeaveApplicationStatus::Pending)
            ->first();

        if (! $application) {
            return;
        }

        $application->update([
            'status' => LeaveApplicationStatus::Cancelled,
            'actioned_by' => auth()->id(),
            'actioned_at' => now(),
        ]);

        Notification::make()
            ->success()
            ->title('Leave application cancelled')
            ->send();
    }

    public function getViewData(): array
    {
        $profile = $this->resolveProfile();

        $applications = $profile
            ? LeaveApplication::query()
                ->where('applicant_type', TeacherProfile::class)
                ->where('applicant_id', $profile->id)
                ->with('leaveType')
                ->orderByDesc('from_date')
                ->get()
            : collect();

        return ['applications' => $applications];
    }

    private function availableLeaveTypes(): Collection
    {
        $profile = $this->resolveProfile();

        if (! $profile) {
            return collect();
        }

        return LeaveType::query()
            ->active()
            ->availableFor($profile)
            ->orderBy('name')
            ->get();
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

        $set('total_days', app(WorkingDaysCalculator::class)->count($fromDate, $toDate));
    }

    private function resolveProfile(): ?TeacherProfile
    {
        return auth()->user()?->teacherProfile;
    }
}
