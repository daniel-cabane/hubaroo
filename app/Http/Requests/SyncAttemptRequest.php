<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncAttemptRequest extends FormRequest
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
            'changes' => ['required', 'array', 'min:1'],
            'changes.*.question_index' => ['required', 'integer', 'min:0', 'max:25'],
            'changes.*.answer' => ['present', 'nullable'],
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
            'changes.*.question_index.max' => 'Each question index must be between 0 and 25.',
            'changes.*.answer.present' => 'Each answer change must include an answer value.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('changes', []) as $index => $change) {
                if (! is_array($change) || ! array_key_exists('answer', $change)) {
                    continue;
                }

                $questionIndex = $change['question_index'] ?? null;
                $answer = $change['answer'];

                if ($answer === null) {
                    continue;
                }

                $isNumericQuestion = in_array((int) $questionIndex, [24, 25], true);

                if ($isNumericQuestion) {
                    if (! is_numeric($answer) || (int) $answer < 0 || (int) $answer > 100) {
                        $validator->errors()->add(
                            "changes.{$index}.answer",
                            'Numeric answers must be integers between 0 and 100.'
                        );
                    }

                    continue;
                }

                if (! in_array($answer, ['A', 'B', 'C', 'D', 'E'], true)) {
                    $validator->errors()->add(
                        "changes.{$index}.answer",
                        'Multiple-choice answers must be A, B, C, D, or E.'
                    );
                }
            }
        });
    }
}
