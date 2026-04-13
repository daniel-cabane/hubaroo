<?php

namespace Database\Factories;

use App\Models\Attempt;
use App\Models\KangourouSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attempt>
 */
class AttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kangourou_session_id' => KangourouSession::factory(),
            'user_id' => null,
            'code' => strtoupper(fake()->bothify('??????')),
            'answers' => Attempt::defaultAnswers(),
            'status' => 'inProgress',
            'score' => null,
        ];
    }

    public function withUser(?User $user = null): static
    {
        return $this->state(fn () => [
            'user_id' => $user?->id ?? User::factory(),
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn () => ['status' => 'finished']);
    }
}
