<?php

namespace App\Filament\Resources\AdmitCards\Pages;

use App\Filament\Resources\AdmitCards\AdmitCardResource;
use App\Models\AdmitCard;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ViewAdmitCard extends ViewRecord
{
    protected static string $resource = AdmitCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label('View')
                ->icon(Heroicon::OutlinedEye)
                ->visible(fn (AdmitCard $record): bool => $record->is_generated && $record->file_path && Storage::disk('local')->exists($record->file_path))
                ->url(fn (AdmitCard $record): string => route('admit-cards.view', $record))
                ->openUrlInNewTab(),
        ];
    }
}
