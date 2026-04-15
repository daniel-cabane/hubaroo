<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Division>
 */
class DivisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teacher_id' => User::factory(),
            'name' => fake()->words(3, true),
            'code' => strtoupper(fake()->bothify('??????')),
            'preferences' => null,
            'accepting_students' => true,
            'archived' => false,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived' => true]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['accepting_students' => false]);
    }
}
