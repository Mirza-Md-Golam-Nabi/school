<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveQuestionBankMarksRequest extends FormRequest
{
    /**
     * Who may send it is AuthenticateQuestionBank's question (a signed request).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'overwrite' => ['required', 'boolean'],
            'marks' => ['required', 'array', 'max:2000'],
            'marks.*.student_id' => ['required', 'integer'],
            'marks.*.absent' => ['required', 'boolean'],
            'marks.*.mcq_marks' => ['nullable', 'numeric', 'required_if:marks.*.absent,false'],
        ];
    }
}
