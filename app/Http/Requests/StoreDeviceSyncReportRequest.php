<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceSyncReportRequest extends FormRequest
{
    /**
     * The device is authenticated by the auth.device middleware before this runs.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * JSON clients send IDs and card numbers as integers; normalise them to strings so
     * the length rules below measure characters, not numeric values.
     */
    protected function prepareForValidation(): void
    {
        $users = $this->input('users');
        $removed = $this->input('removed');

        $this->merge([
            'users' => is_array($users) ? array_map(function (mixed $user): mixed {
                if (! is_array($user)) {
                    return $user;
                }

                foreach (['enroll_id', 'card_number'] as $key) {
                    if (isset($user[$key]) && is_scalar($user[$key])) {
                        $user[$key] = (string) $user[$key];
                    }
                }

                return $user;
            }, $users) : $users,
            'removed' => is_array($removed) ? array_map(fn (mixed $id): mixed => is_scalar($id) ? (string) $id : $id, $removed) : $removed,
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'users' => ['present', 'array', 'max:5000'],
            'users.*.enroll_id' => ['required', 'string', 'max:50'],
            'users.*.name' => ['nullable', 'string', 'max:100'],
            'users.*.card_number' => ['nullable', 'string', 'max:50'],
            'users.*.fingerprint_count' => ['nullable', 'integer', 'min:0', 'max:10'],
            'removed' => ['nullable', 'array', 'max:5000'],
            'removed.*' => ['string', 'max:50'],
            'sizes' => ['nullable', 'array'],
            'sizes.users' => ['nullable', 'integer', 'min:0'],
            'sizes.users_cap' => ['nullable', 'integer', 'min:0'],
            'sizes.fingers' => ['nullable', 'integer', 'min:0'],
            'sizes.fingers_cap' => ['nullable', 'integer', 'min:0'],
            'sizes.cards' => ['nullable', 'integer', 'min:0'],
            'sizes.cards_cap' => ['nullable', 'integer', 'min:0'],
            'sizes.records' => ['nullable', 'integer', 'min:0'],
            'sizes.records_cap' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
