<?php

namespace App\Http\Requests;

use App\Enums\SmsVendorDriver;
use App\Support\CentralSignature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SyncSmsResponseCodesRequest extends FormRequest
{
    /**
     * Provider response codes are written only by the central app
     * (NextFusion). The signature covers the raw body (X-Signature =
     * HMAC-SHA256 of "{X-Timestamp}.{body}"), so the list can't be altered on
     * the way.
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'codes' => ['present', 'array'],
            'codes.*.driver' => ['required', Rule::enum(SmsVendorDriver::class)],
            'codes.*.code' => ['required', 'string', 'max:20'],
            'codes.*.message' => ['required', 'string', 'max:255'],
            'codes.*.is_success' => ['required', 'boolean'],
        ];
    }

    /**
     * The same provider code twice would make the copy ambiguous.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $keys = collect($this->input('codes', []))
                    ->map(fn (mixed $code): string => is_array($code) ? ($code['driver'] ?? '').'|'.($code['code'] ?? '') : '');

                if ($keys->duplicates()->isNotEmpty()) {
                    $validator->errors()->add('codes', 'একই প্রোভাইডারের একই কোড একাধিকবার পাঠানো হয়েছে।');
                }
            },
        ];
    }
}
