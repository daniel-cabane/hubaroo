<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Question;
use App\Models\SuggestedQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuggestedQuestion>
 */
class SuggestedQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'question_id' => Question::factory(),
            'level' => fake()->numberBetween(1, 3),
            'is_public' => false,
        ];
    }

    public function public(): static
    {
        return $this->state(fn () => ['is_public' => true]);
    }
}
