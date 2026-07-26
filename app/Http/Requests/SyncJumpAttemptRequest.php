<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncJumpAttemptRequest extends FormRequest
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
            'changes' => ['required', 'array', 'min:1'],
            'changes.*.question_index' => ['required', 'integer', 'min:0'],
            'changes.*.answer' => ['present', 'nullable', 'string', 'max:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'changes.required' => 'At least one answer change must be provided.',
            'changes.array' => 'Answer changes must be provided as an array.',
            'changes.min' => 'At least one answer change must be provided.',
            'changes.*.question_index.required' => 'Each answer change must target a question index.',
            'changes.*.question_index.integer' => 'Each question index must be an integer.',
            'changes.*.answer.present' => 'Each answer change must include an answer value.',
            'changes.*.answer.string' => 'Each answer value must be a string or null.',
        ];
    }
}
