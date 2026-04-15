<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\DivisionInvite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DivisionInvite>
 */
class DivisionInviteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'email' => fake()->safeEmail(),
            'user_id' => null,
            'status' => 'pending',
        ];
    }
}
