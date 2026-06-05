<?php

namespace App\Filament\Resources\Notices\Pages;

use App\Actions\Notice\SyncNoticeTargetsAction;
use App\Filament\Resources\Notices\NoticeResource;
use App\Jobs\PublishNoticeJob;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditNotice extends EditRecord
{
    protected static string $resource = NoticeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Pre-populate target_ids so the Select shows existing selections.
        $data['target_ids'] = $this->record->targets()->pluck('targetable_id')->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // No schedule set → publish immediately
        if (empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $targetIds = array_filter((array) ($this->data['target_ids'] ?? []));
        app(SyncNoticeTargetsAction::class)->handle($this->record, $targetIds);

        if ($this->record->wasChanged('published_at') && $this->record->isScheduled()) {
            PublishNoticeJob::dispatch($this->record->id)
                ->delay($this->record->published_at);
        }
    }
}
