<?php

namespace App\Filament\Resources\StudentProfiles\Pages;

use App\Filament\Resources\StudentProfiles\Concerns\HasResetPasswordAction;
use App\Filament\Resources\StudentProfiles\StudentProfileResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewStudentProfile extends ViewRecord
{
    use HasResetPasswordAction;

    protected static string $resource = StudentProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => StudentProfileResource::getUrl()),

            $this->resetPasswordAction('danger'),

            EditAction::make(),
        ];
    }
}
