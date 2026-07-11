<?php

namespace App\Filament\Pages;

use App\Models\AttendanceStatusChange;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

class AttendanceModificationDetails extends Page
{
    protected string $view = 'filament.pages.attendance-modification-details';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'batch')]
    public string $batchId = '';

    public function getTitle(): string|Htmlable
    {
        return 'Attendance Modification Details';
    }

    public function getBreadcrumbs(): array
    {
        return [
            '' => 'Attendance Modification Details',
        ];
    }

    public function getChanges(): Collection
    {
        return AttendanceStatusChange::query()
            ->where('batch_id', $this->batchId)
            ->with(['studentProfile.user', 'changedBy', 'class'])
            ->orderBy('created_at')
            ->get();
    }
}
