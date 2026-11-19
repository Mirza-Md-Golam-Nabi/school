<?php

namespace App\Filament\Resources\Notices\Pages;

use App\Actions\Notice\SyncNoticeTargetsAction;
use App\Actions\Sms\SendNoticeSmsAction;
use App\Enums\SmsEvent;
use App\Filament\Resources\Notices\NoticeResource;
use App\Jobs\PublishNoticeJob;
use App\Models\SmsSetting;
use App\Notifications\Concerns\NotifiesNoticeCreated;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateNotice extends CreateRecord
{
    use NotifiesNoticeCreated;

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

    /**
     * A notice published right now with SMS must be affordable in full before
     * it is saved — otherwise the admin would publish it believing the SMS
     * went out. (A scheduled notice is checked again when it publishes.)
     */
    protected function beforeCreate(): void
    {
        $isImmediate = empty($this->data['published_at']) || now()->gte($this->data['published_at']);

        if (! ($this->data['send_sms'] ?? false) || ! $isImmediate || ! SmsSetting::current()->isEventEnabled(SmsEvent::Notice)) {
            return;
        }

        $estimate = app(SendNoticeSmsAction::class)->estimate(
            $this->data['target_type'] ?? null,
            array_filter((array) ($this->data['target_ids'] ?? [])),
            $this->data['title'] ?? '',
            $this->data['body'] ?? '',
        );

        if ($estimate['is_affordable']) {
            return;
        }

        Notification::make()
            ->danger()
            ->title('SMS ব্যালেন্স অপর্যাপ্ত')
            ->body(sprintf(
                '%d জনকে SMS পাঠাতে ৳%s লাগবে, ব্যালেন্স আছে ৳%s। রিচার্জ করুন, অথবা "Send SMS" বন্ধ করে প্রকাশ করুন।',
                $estimate['recipients'],
                number_format($estimate['cost'], 2),
                number_format($estimate['balance'], 2),
            ))
            ->persistent()
            ->send();

        $this->halt();
    }

    protected function afterCreate(): void
    {
        $targetIds = array_filter((array) ($this->data['target_ids'] ?? []));
        app(SyncNoticeTargetsAction::class)->handle($this->record, $targetIds);

        if ($this->record->isScheduled()) {
            PublishNoticeJob::dispatch($this->record->id)
                ->delay($this->record->published_at);
        } elseif ($this->record->isPublished()) {
            $smsResult = $this->notifyNoticeCreated($this->record);

            if ($smsResult?->isRejected()) {
                Notification::make()->danger()->title('SMS পাঠানো যায়নি')->body($smsResult->rejectionMessage())->persistent()->send();
            }
        }
    }
}
