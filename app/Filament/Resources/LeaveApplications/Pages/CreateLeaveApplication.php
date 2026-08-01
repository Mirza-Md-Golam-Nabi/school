<?php

namespace App\Filament\Resources\LeaveApplications\Pages;

use App\Filament\Resources\LeaveApplications\LeaveApplicationResource;
use App\Models\LeaveTypeAssignment;
use Filament\Resources\Pages\CreateRecord;

class CreateLeaveApplication extends CreateRecord
{
    protected static string $resource = LeaveApplicationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['applied_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $leaveType = $this->record->leaveType;
        $applicant = $this->record->applicant;

        if ($leaveType?->requiresAssignment() && $applicant) {
            LeaveTypeAssignment::ensureAssigned($leaveType, $applicant, auth()->id());
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
