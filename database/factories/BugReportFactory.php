<?php

namespace Database\Factories;

use App\Models\BugReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BugReport>
 */
class BugReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'comment' => fake()->paragraph(),
            'status' => 'new',
        ];
    }

    public function fixed(): static
    {
        return $this->state(fn () => ['status' => 'fixed']);
    }

    public function irrelevant(): static
    {
        return $this->state(fn () => ['status' => 'irrelevant']);
    }
}
