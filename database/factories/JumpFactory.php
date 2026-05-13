<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Jump;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Jump>
 */
class JumpFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'nb_questions' => 7,
            'time' => 15,
            'status' => 'draft',
            'expiration' => null,
            'growth' => 3,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'expiration' => now()->addMinutes(30),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'expired',
            'expiration' => now()->subMinutes(5),
        ]);
    }
}
