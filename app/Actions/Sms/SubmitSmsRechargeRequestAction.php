<?php

namespace App\Actions\Sms;

use App\Enums\SmsRechargeRequestStatus;
use App\Models\SchoolSetting;
use App\Models\SmsRechargeRequest;
use App\Models\SmsSetting;
use App\Models\User;
use App\Support\CentralSignature;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SubmitSmsRechargeRequestAction
{
    /**
     * Saves an admin's recharge request and forwards it to the recharge
     * request link. The balance itself is only added from outside, once the
     * payment is confirmed (CreditSmsBalanceAction).
     */
    public function handle(float $amount, ?string $note, ?User $requestedBy): SmsRechargeRequest
    {
        $setting = SmsSetting::current();

        $request = SmsRechargeRequest::create([
            'amount' => round($amount, 2),
            'rate_per_sms' => $setting->ratePerSms(),
            'estimated_sms' => $setting->smsCountFor($amount),
            'note' => filled($note) ? $note : null,
            'status' => SmsRechargeRequestStatus::Pending,
            'requested_by' => $requestedBy?->id,
        ]);

        $this->deliver($request);

        return $request;
    }

    /**
     * POSTs the request to the configured link. Returns false (and records
     * why) when there is no link yet or it could not be reached.
     */
    public function deliver(SmsRechargeRequest $request): bool
    {
        $url = (string) config('sms.recharge_request_url');

        if (blank($url)) {
            $request->update(['delivery_error' => 'Recharge request link is not configured yet.']);

            return false;
        }

        $request->loadMissing('requestedBy:id,name,phone,email');

        $body = json_encode([
            'school_id' => (string) config('central.school_id'),
            'school_name' => (string) SchoolSetting::get('school_name', ''),
            'school_url' => (string) config('app.url'),
            'request_id' => $request->id,
            'amount' => (float) $request->amount,
            'rate_per_sms' => (float) $request->rate_per_sms,
            'estimated_sms' => $request->estimated_sms,
            'note' => $request->note,
            'requested_by' => $request->requestedBy?->only(['name', 'phone', 'email']),
            'requested_at' => $request->created_at?->toIso8601String(),
        ]);

        $timestamp = (string) now()->timestamp;
        $headers = ['X-School-Id' => (string) config('central.school_id'), 'X-Timestamp' => $timestamp];

        if (CentralSignature::isConfigured()) {
            $headers['X-Signature'] = CentralSignature::sign($timestamp, $body);
        }

        try {
            $response = Http::withHeaders($headers)
                ->withBody($body, 'application/json')
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(20)
                ->post($url);
        } catch (ConnectionException $exception) {
            $request->update(['delivery_error' => mb_substr($exception->getMessage(), 0, 500)]);

            return false;
        }

        if ($response->failed()) {
            $request->update(['delivery_error' => "HTTP {$response->status()}: ".mb_substr($response->body(), 0, 300)]);

            return false;
        }

        $request->update(['delivered_at' => now(), 'delivery_error' => null]);

        return true;
    }
}
