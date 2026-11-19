<?php

namespace App\Actions\Sms;

use App\Enums\NoticeTargetType;
use App\Enums\SmsEvent;
use App\Models\Notice;
use App\Models\SmsSetting;
use App\Support\Sms\BangladeshPhone;
use App\Support\Sms\OutgoingSms;
use App\Support\Sms\SmsBatchResult;
use App\Support\Sms\SmsSegments;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Collection;

class SendNoticeSmsAction
{
    public function __construct(
        private QueueSmsBatchAction $queueSmsBatch,
        private ResolveNoticeSmsRecipientsAction $resolveRecipients,
    ) {}

    /**
     * Sends a published notice's SMS to its whole audience as one
     * all-or-nothing batch, when the notice asked for SMS and notice SMS is on.
     */
    public function handle(Notice $notice): ?SmsBatchResult
    {
        $setting = SmsSetting::current();

        if (! $notice->send_sms || ! $setting->isEventEnabled(SmsEvent::Notice)) {
            return null;
        }

        $message = $this->message($setting, $notice->title, $notice->body);

        $messages = $this->recipients($notice)->map(fn (array $recipient): OutgoingSms => new OutgoingSms(
            phone: $recipient['phone'],
            message: $message,
            event: SmsEvent::Notice,
            sourceId: $notice->id,
            recipientName: $recipient['name'],
        ));

        return $messages->isEmpty() ? null : $this->queueSmsBatch->handle($messages);
    }

    /**
     * What sending this notice would cost, for the form and the pre-save check.
     *
     * @param  array<int|string>  $targetIds
     * @param  string|array<string, mixed>|null  $body  HTML, or the rich editor's raw (JSON document) form state.
     * @return array{recipients: int, parts: int, cost: float, balance: float, is_affordable: bool}
     */
    public function estimate(mixed $targetType, array $targetIds, ?string $title, string|array|null $body): array
    {
        $setting = SmsSetting::current();
        $html = is_array($body) ? RichContentRenderer::make($body)->toHtml() : (string) $body;
        $message = $this->message($setting, (string) $title, $html);

        $targetType = $targetType instanceof NoticeTargetType ? $targetType : NoticeTargetType::tryFrom((string) $targetType);

        $recipients = $this->resolveRecipients->handle($targetType, $targetIds)
            ->map(fn (array $recipient): ?string => BangladeshPhone::normalize($recipient['phone']))
            ->filter()
            ->unique()
            ->count();

        $parts = $recipients * SmsSegments::count($message);
        $cost = round($parts * $setting->ratePerSms(), 4);

        return [
            'recipients' => $recipients,
            'parts' => $parts,
            'cost' => $cost,
            'balance' => $setting->currentBalance(),
            'is_affordable' => $setting->currentBalance() + 1e-9 >= $cost,
        ];
    }

    /**
     * @return Collection<int, array{name: string, phone: ?string}>
     */
    private function recipients(Notice $notice): Collection
    {
        return $this->resolveRecipients->handle($notice->target_type, $notice->targets()->pluck('targetable_id')->all());
    }

    private function message(SmsSetting $setting, string $title, string $body): string
    {
        $plainBody = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ' ', $body)), ENT_QUOTES | ENT_HTML5)));

        return $setting->render(SmsEvent::Notice, ['title' => $title, 'body' => $plainBody]);
    }
}
