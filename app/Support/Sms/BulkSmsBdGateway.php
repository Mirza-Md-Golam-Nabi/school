<?php

namespace App\Support\Sms;

use App\Enums\SmsVendorDriver;
use App\Models\SmsVendor;
use App\Models\SmsVendorResponseCode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * bulksmsbd.net — GET {base}/smsapi?api_key=&type=text&number=&senderid=&message=
 * and GET {base}/getBalanceApi?api_key=. Whether a reply's response_code means
 * success, and what it means, comes from the sms_vendor_response_codes table.
 */
class BulkSmsBdGateway implements SmsGateway
{
    private const SUCCESS_CODE = '202';

    public function __construct(private SmsVendor $vendor) {}

    public function send(string $phone, string $message): SmsSendResult
    {
        try {
            $response = $this->client()->get('/smsapi', [
                'api_key' => $this->vendor->api_key,
                'type' => 'text',
                'number' => $phone,
                'senderid' => $this->vendor->sender_id,
                'message' => $message,
            ]);
        } catch (ConnectionException $exception) {
            return SmsSendResult::failure('Connection failed: '.$exception->getMessage());
        }

        $code = $response->json('response_code');
        $code = $code === null ? null : (string) $code;
        $known = SmsVendorResponseCode::lookup(SmsVendorDriver::BulkSmsBd, $code);

        // The response code table decides; 202 is the documented fallback when
        // the table has not been seeded yet.
        $isSuccess = $known?->is_success ?? ($code === self::SUCCESS_CODE);

        if ($response->successful() && $isSuccess) {
            $messageId = $response->json('message_id');

            return SmsSendResult::success($messageId === null ? null : (string) $messageId, $code);
        }

        $reason = $known?->message
            ?: $response->json('error_message')
            ?: $response->json('success_message')
            ?: "HTTP {$response->status()}: ".mb_substr($response->body(), 0, 200);

        return SmsSendResult::failure(trim('['.($code ?? '—').'] '.$reason), $code);
    }

    public function balance(): ?float
    {
        try {
            $response = $this->client()->get('/getBalanceApi', ['api_key' => $this->vendor->api_key]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $balance = $response->json('balance');

        if ($balance === null && is_numeric(trim($response->body()))) {
            $balance = trim($response->body());
        }

        return is_numeric($balance) ? (float) $balance : null;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('sms.drivers.bulksmsbd.base_url'))
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30);
    }
}
