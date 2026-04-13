<?php

namespace Database\Factories;

use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'image' => 'questions/'.fake()->uuid().'.png',
            'correct_answer' => fake()->randomElement(['A', 'B', 'C', 'D', 'E']),
            'tier' => fake()->numberBetween(1, 4),
        ];
    }
}
