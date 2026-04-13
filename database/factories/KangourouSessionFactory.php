<?php

namespace Database\Factories;

use App\Models\KangourouSession;
use App\Models\Paper;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KangourouSession>
 */
class KangourouSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'paper_id' => Paper::factory(),
            'code' => strtoupper(fake()->bothify('??????')),
            'author_id' => null,
            'status' => 'active',
            'privacy' => 'public',
            'expires_at' => now()->addMinutes(120),
            'preferences' => KangourouSession::DEFAULT_PREFERENCES,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'expired',
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function withAuthor(?User $user = null): static
    {
        return $this->state(fn () => [
            'author_id' => $user?->id ?? User::factory(),
        ]);
    }
}
