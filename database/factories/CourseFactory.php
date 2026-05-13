<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'title' => fake()->words(3, true),
            'archived' => false,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived' => true]);
    }
}
