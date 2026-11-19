<?php

namespace App\Http\Requests;

use App\Enums\SmsVendorDriver;
use App\Support\CentralSecretBox;
use App\Support\CentralSignature;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SyncSmsVendorsRequest extends FormRequest
{
    /**
     * SMS vendors are set only by the central app (NextFusion). The signature
     * covers the raw body (X-Signature = HMAC-SHA256 of "{X-Timestamp}.{body}"),
     * and each API key is additionally sealed with this school's secret
     * (CentralSecretBox).
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
            'vendors' => ['present', 'array'],
            'vendors.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'vendors.*.name' => ['required', 'string', 'max:100'],
            'vendors.*.driver' => ['required', Rule::enum(SmsVendorDriver::class)],
            'vendors.*.api_key' => ['required', 'string'],
            'vendors.*.sender_id' => ['required', 'string', 'max:20'],
            'vendors.*.priority' => ['required', 'integer', 'min:1', 'max:99'],
            'vendors.*.is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * An API key that doesn't open with this school's secret was not meant
     * for this school.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('vendors', []) as $index => $vendor) {
                    if (! is_array($vendor) || ! is_string($vendor['api_key'] ?? null)) {
                        continue;
                    }

                    try {
                        CentralSecretBox::open($vendor['api_key']);
                    } catch (DecryptException) {
                        $validator->errors()->add("vendors.{$index}.api_key", 'API key এই স্কুলের secret দিয়ে খোলা যায়নি।');
                    }
                }
            },
        ];
    }

    /**
     * The validated vendors with their API keys opened.
     *
     * @return list<array{id: int, name: string, driver: string, api_key: string, sender_id: string, priority: int, is_active: bool}>
     */
    public function vendors(): array
    {
        return collect($this->validated('vendors'))
            ->map(fn (array $vendor): array => [
                'id' => (int) $vendor['id'],
                'name' => $vendor['name'],
                'driver' => $vendor['driver'],
                'api_key' => CentralSecretBox::open($vendor['api_key']),
                'sender_id' => $vendor['sender_id'],
                'priority' => (int) $vendor['priority'],
                'is_active' => (bool) $vendor['is_active'],
            ])
            ->values()
            ->all();
    }
}
