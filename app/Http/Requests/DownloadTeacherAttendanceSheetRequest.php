<?php

namespace App\Http\Requests;

use Filament\Facades\Filament;
use Illuminate\Foundation\Http\FormRequest;

class DownloadTeacherAttendanceSheetRequest extends FormRequest
{
    /**
     * Only people who can use the admin panel, where the report page lives.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->canAccessPanel(Filament::getPanel('admin'));
    }

    /**
     * The year and month are part of the URL; validate them like form input.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'year' => $this->route('year'),
            'month' => $this->route('month'),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ];
    }
}
