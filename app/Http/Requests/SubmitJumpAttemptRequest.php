<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitJumpAttemptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->id === $this->route('jumpAttempt')?->user_id;
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
            'question_list' => ['nullable', 'array'],
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
            'question_list.array' => 'The submitted question list must be an array.',
        ];
    }
}
