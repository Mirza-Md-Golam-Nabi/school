<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDevicePunchesRequest extends FormRequest
{
    /**
     * The device is authenticated by the auth.device middleware before this runs.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * JSON clients often send numeric enroll IDs as integers; the max-length rule
     * below only measures length on strings, so normalise them first.
     */
    protected function prepareForValidation(): void
    {
        $punches = $this->input('punches');

        if (! is_array($punches)) {
            return;
        }

        $this->merge([
            'punches' => array_map(function (mixed $punch): mixed {
                if (is_array($punch) && isset($punch['enroll_id']) && is_scalar($punch['enroll_id'])) {
                    $punch['enroll_id'] = (string) $punch['enroll_id'];
                }

                return $punch;
            }, $punches),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'punches' => ['required', 'array', 'min:1', 'max:1000'],
            'punches.*.enroll_id' => ['required', 'string', 'max:50'],
            'punches.*.punched_at' => ['required', 'date'],
            'punches.*.verify_type' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'punches.*.state' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
