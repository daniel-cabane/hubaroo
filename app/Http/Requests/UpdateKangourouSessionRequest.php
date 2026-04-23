<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateKangourouSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->id === $this->route('kangourouSession')->author_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'privacy' => ['sometimes', 'in:public,private'],
            'expires_at' => ['sometimes', 'date'],
            'preferences' => ['sometimes', 'array'],
            'preferences.time_limit' => ['sometimes', 'integer', 'min:1', 'max:300'],
            'preferences.blur_security' => ['sometimes', 'boolean'],
            'preferences.only_count_tier4_if_all_before_correct' => ['sometimes', 'boolean'],
            'preferences.shuffle' => ['sometimes', 'in:none,by_tier,tiers_1_3,complete'],
            'preferences.correction' => ['sometimes', 'in:immediate,delayed'],
            'preferences.grading' => ['sometimes', 'array'],
            'preferences.grading.tier1' => ['sometimes', 'numeric', 'min:0'],
            'preferences.grading.tier2' => ['sometimes', 'numeric', 'min:0'],
            'preferences.grading.tier3' => ['sometimes', 'numeric', 'min:0'],
            'preferences.grading.tier4_bonus' => ['sometimes', 'numeric', 'min:0'],
            'preferences.grading.penalty_fraction' => ['sometimes', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
