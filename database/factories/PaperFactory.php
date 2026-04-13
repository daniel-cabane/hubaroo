<?php

namespace Database\Factories;

use App\Models\Paper;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paper>
 */
class PaperFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $level = fake()->randomElement(['e', 'b', 'c', 'p', 'j', 's']);
        $year = fake()->numberBetween(2004, 2025);
        $levelNames = ['e' => 'Ecoliers', 'b' => 'Benjamin', 'c' => 'Cadet', 'p' => 'Junior (P)', 'j' => 'Junior', 's' => 'Student'];

        return [
            'title' => 'Kangourou '.$levelNames[$level].' '.$year,
            'level' => $level,
            'year' => $year,
        ];
    }

    /**
     * Create a paper with 26 questions attached.
     */
    public function withQuestions(): static
    {
        return $this->afterCreating(function (Paper $paper) {
            for ($i = 1; $i <= 26; $i++) {
                $tier = $i <= 8 ? 1 : ($i <= 16 ? 2 : ($i <= 24 ? 3 : 4));
                $question = Question::factory()->create(['tier' => $tier]);
                $paper->questions()->attach($question->id, ['order' => $i]);
            }
        });
    }
}
