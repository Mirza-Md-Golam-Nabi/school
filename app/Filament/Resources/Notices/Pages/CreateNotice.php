<?php

namespace App\Filament\Resources\Notices\Pages;

use App\Actions\Notice\SyncNoticeTargetsAction;
use App\Filament\Resources\Notices\NoticeResource;
use App\Jobs\PublishNoticeJob;
use Filament\Resources\Pages\CreateRecord;

class CreateNotice extends CreateRecord
{
    protected static string $resource = NoticeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        // No schedule set → publish immediately
        if (empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $targetIds = array_filter((array) ($this->data['target_ids'] ?? []));
        app(SyncNoticeTargetsAction::class)->handle($this->record, $targetIds);

        if ($this->record->isScheduled()) {
            PublishNoticeJob::dispatch($this->record->id)
                ->delay($this->record->published_at);
        }
    }
}
