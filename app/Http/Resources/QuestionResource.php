<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    public function __construct($resource, public bool $showAnswers = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        if (! $this->showAnswers) {
            unset($data['correct_answer']);
        }

        return $data;
    }
}
