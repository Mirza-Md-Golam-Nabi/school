<?php

namespace App\Filament\Resources\Marksheets\Pages;

use App\Filament\Resources\Marksheets\MarksheetResource;
use App\Models\Marksheet;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ViewMarksheet extends ViewRecord
{
    protected static string $resource = MarksheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label('View')
                ->icon(Heroicon::OutlinedEye)
                ->visible(fn (Marksheet $record): bool => $record->is_generated && $record->file_path && Storage::disk('local')->exists($record->file_path))
                ->url(fn (Marksheet $record): string => route('marksheets.view', $record))
                ->openUrlInNewTab(),
        ];
    }
}
