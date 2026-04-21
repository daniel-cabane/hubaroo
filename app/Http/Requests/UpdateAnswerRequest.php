<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAnswerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isNumericQuestion = in_array((int) $this->input('question_index'), [24, 25]);

        return [
            'question_index' => ['required', 'integer', 'min:0', 'max:25'],
            'answer' => $isNumericQuestion
                ? ['nullable', 'integer', 'min:0', 'max:100']
                : ['nullable', 'in:A,B,C,D,E'],
            'timer' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
