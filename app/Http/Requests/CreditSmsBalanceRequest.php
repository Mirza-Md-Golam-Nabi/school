<?php

namespace App\Http\Requests;

use App\Support\CentralSignature;
use Illuminate\Foundation\Http\FormRequest;

class CreditSmsBalanceRequest extends FormRequest
{
    /**
     * Only the central app may add SMS balance. Because this request moves
     * money, the signature covers the raw body itself (X-Signature =
     * HMAC-SHA256 of "{X-Timestamp}.{body}"), so the amount can't be altered.
     */
    public function authorize(): bool
    {
        return CentralSignature::verify(
            $this->header('X-Timestamp'),
            $this->getContent(),
            $this->header('X-Signature'),
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'reference' => ['required', 'string', 'max:191'],
            'recharge_request_id' => ['nullable', 'integer', 'exists:sms_recharge_requests,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
