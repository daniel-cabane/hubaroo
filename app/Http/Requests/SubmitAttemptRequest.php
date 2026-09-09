<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitAttemptRequest extends FormRequest
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
        return [
            'timer' => ['nullable', 'integer', 'min:0'],
            'termination' => ['nullable', 'string', 'in:submitted,timeout,blurred,abandoned'],
            'answers' => ['nullable', 'array'],
            'answers.*.answer' => ['present', 'nullable'],
            'answers.*.status' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'timer.integer' => 'The remaining timer must be an integer number of seconds.',
            'timer.min' => 'The remaining timer cannot be negative.',
            'termination.in' => 'The termination reason is invalid.',
            'answers.array' => 'The submitted answers must be an array.',
        ];
    }
}
