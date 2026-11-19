<?php

namespace App\Actions\Sms;

use App\Enums\Permissions\SmsPermission;
use App\Enums\SmsBalanceTransactionType;
use App\Enums\SmsStatus;
use App\Filament\Resources\SmsRechargeRequests\SmsRechargeRequestResource;
use App\Jobs\SendSmsJob;
use App\Models\SmsLog;
use App\Models\SmsSetting;
use App\Models\SmsVendor;
use App\Models\User;
use App\Support\Sms\BangladeshPhone;
use App\Support\Sms\OutgoingSms;
use App\Support\Sms\SmsBatchResult;
use App\Support\Sms\SmsSegments;
use App\Support\Sms\SmsWallet;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * The single entry point for sending SMS. It is all or nothing: the whole
 * batch's cost is checked against the school's SMS balance, and either every
 * message is charged and queued, or none is.
 */
class QueueSmsBatchAction
{
    public const SHORTFALL_ALERT_CACHE_KEY = 'sms:shortfall-alert';

    /**
     * @param  iterable<OutgoingSms>  $messages
     */
    public function handle(iterable $messages, bool $alertAdminsOnRejection = true): SmsBatchResult
    {
        $setting = SmsSetting::current();
        [$deliverable, $invalidCount] = $this->deliverable($messages);

        if ($deliverable->isEmpty()) {
            return new SmsBatchResult(SmsBatchResult::NOTHING_TO_SEND, skippedInvalidPhone: $invalidCount);
        }

        if (! $setting->is_enabled || $setting->ratePerSms() <= 0) {
            return new SmsBatchResult(SmsBatchResult::DISABLED, skippedInvalidPhone: $invalidCount);
        }

        $parts = (int) $deliverable->sum('parts');
        $cost = round($parts * $setting->ratePerSms(), 4);
        $vendors = SmsVendor::query()->usable()->get();

        $rejection = match (true) {
            $vendors->isEmpty() => SmsBatchResult::NO_VENDOR,
            $deliverable->count() > 1 && ! $this->providersCanCover($vendors, $cost) => SmsBatchResult::PROVIDER_UNAVAILABLE,
            default => null,
        };

        if ($rejection !== null) {
            $result = new SmsBatchResult($rejection, $deliverable->count(), $invalidCount, $parts, $cost, $setting->currentBalance());

            return $this->rejected($result, $alertAdminsOnRejection);
        }

        $batchId = (string) Str::uuid();

        $result = SmsWallet::locked(function (SmsSetting $locked) use ($deliverable, $invalidCount, $parts, $cost, $batchId): SmsBatchResult {
            if ($locked->currentBalance() + 1e-9 < $cost) {
                return new SmsBatchResult(SmsBatchResult::INSUFFICIENT_BALANCE, $deliverable->count(), $invalidCount, $parts, $cost, $locked->currentBalance());
            }

            SmsWallet::apply($locked, SmsBalanceTransactionType::Debit, -$cost, [
                'sms_parts' => $parts,
                'batch_id' => $batchId,
                'description' => $this->describe($deliverable),
            ]);

            $this->createLogs($deliverable, $locked->ratePerSms(), $batchId);

            return new SmsBatchResult(SmsBatchResult::QUEUED, $deliverable->count(), $invalidCount, $parts, $cost, $locked->currentBalance());
        });

        if (! $result->isQueued()) {
            return $this->rejected($result, $alertAdminsOnRejection);
        }

        SmsLog::query()->where('batch_id', $batchId)->pluck('id')
            ->each(fn (int $id) => SendSmsJob::dispatch($id)->afterCommit());

        return $result;
    }

    /**
     * Normalized, de-duplicated messages (the same text to the same number is
     * sent once — e.g. a guardian of two students receiving one notice).
     *
     * @param  iterable<OutgoingSms>  $messages
     * @return array{0: Collection<int, array{sms: OutgoingSms, phone: string, parts: int}>, 1: int}
     */
    private function deliverable(iterable $messages): array
    {
        $deliverable = collect();
        $invalid = 0;

        foreach ($messages as $sms) {
            $phone = BangladeshPhone::normalize($sms->phone);

            if ($phone === null || trim($sms->message) === '') {
                $invalid++;

                continue;
            }

            $key = $phone.'|'.$sms->message;

            if ($deliverable->has($key)) {
                continue;
            }

            $deliverable->put($key, ['sms' => $sms, 'phone' => $phone, 'parts' => SmsSegments::count($sms->message)]);
        }

        return [$deliverable->values(), $invalid];
    }

    /**
     * Whether at least one provider account can pay for this batch. The real
     * balance is only compared here and never surfaced. An unreadable balance
     * counts as "can cover" so a flaky balance API never blocks sending.
     *
     * @param  Collection<int, SmsVendor>  $vendors
     */
    private function providersCanCover(Collection $vendors, float $cost): bool
    {
        return $vendors->contains(function (SmsVendor $vendor) use ($cost): bool {
            try {
                $balance = Cache::remember(
                    "sms:vendor-balance:{$vendor->id}",
                    (int) config('sms.provider_balance_cache_seconds'),
                    fn (): ?float => $vendor->gateway()->balance(),
                );
            } catch (Throwable) {
                return true;
            }

            return $balance === null || $balance >= $cost;
        });
    }

    /**
     * @param  Collection<int, array{sms: OutgoingSms, phone: string, parts: int}>  $deliverable
     */
    private function createLogs(Collection $deliverable, float $rate, string $batchId): void
    {
        $now = now();

        $deliverable->chunk(500)->each(function (Collection $chunk) use ($rate, $batchId, $now): void {
            SmsLog::query()->insert($chunk->map(fn (array $item): array => [
                'source_type' => $item['sms']->event->source()->value,
                'source_id' => $item['sms']->sourceId,
                'event' => $item['sms']->event->value,
                'phone' => $item['phone'],
                'recipient_name' => $item['sms']->recipientName === null ? null : Str::limit($item['sms']->recipientName, 250),
                'message' => $item['sms']->message,
                'parts' => $item['parts'],
                'cost' => round($item['parts'] * $rate, 4),
                'batch_id' => $batchId,
                'status' => SmsStatus::Pending->value,
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all());
        });
    }

    /**
     * @param  Collection<int, array{sms: OutgoingSms, phone: string, parts: int}>  $deliverable
     */
    private function describe(Collection $deliverable): string
    {
        $events = $deliverable->map(fn (array $item): string => $item['sms']->event->getLabel())->unique()->implode(', ');

        return "{$deliverable->count()} SMS — {$events}";
    }

    private function rejected(SmsBatchResult $result, bool $alertAdmins): SmsBatchResult
    {
        if ($alertAdmins && Cache::add(self::SHORTFALL_ALERT_CACHE_KEY, true, now()->addMinutes((int) config('sms.shortfall_alert_minutes')))) {
            $recipients = User::query()
                ->where('is_active', true)
                ->where(fn (Builder $query) => $query
                    ->whereHas('roles', fn (Builder $role) => $role->whereIn('name', ['admin', 'super-admin']))
                    ->orWhereHas('roles.permissions', fn (Builder $permission) => $permission->where('name', SmsPermission::MANAGE_SMS_SETTINGS->value)))
                ->get();

            if ($recipients->isNotEmpty()) {
                Notification::make()
                    ->danger()
                    ->title('SMS পাঠানো যায়নি')
                    ->body($result->rejectionMessage())
                    ->actions([
                        Action::make('recharge')
                            ->label('Recharge')
                            ->url(SmsRechargeRequestResource::getUrl('index', panel: 'admin')),
                    ])
                    ->sendToDatabase($recipients);
            }
        }

        return $result;
    }
}
