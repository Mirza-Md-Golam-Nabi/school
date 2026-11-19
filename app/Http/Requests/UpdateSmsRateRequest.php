<?php

namespace App\Http\Requests;

use App\Support\CentralSignature;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSmsRateRequest extends FormRequest
{
    /**
     * The per-SMS charge is set only by the central app (NextFusion). The
     * signature covers the raw body (X-Signature = HMAC-SHA256 of
     * "{X-Timestamp}.{body}"), so the rate can't be altered on the way.
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
            'rate_per_sms' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
