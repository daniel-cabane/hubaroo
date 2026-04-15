<?php

namespace Database\Seeders;

use App\Models\Attempt;
use App\Models\KangourouSession;
use App\Models\Paper;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class KangourouSessionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some teachers (should exist from DatabaseSeeder)
        $teachers = User::role('Teacher')->limit(3)->get();

        if ($teachers->isEmpty()) {
            $teachers = User::factory(3)
                ->withSecretPassword()
                ->create()
                ->each(fn (User $user) => $user->assignRole('Teacher'));
        }

        // Get some students for attempts
        $students = User::role('Student')->limit(15)->get();

        if ($students->count() < 15) {
            $additionalStudents = User::factory(15 - $students->count())
                ->withSecretPassword()
                ->create()
                ->each(fn (User $user) => $user->assignRole('Student'));
            $students = $students->merge($additionalStudents);
        }

        // Get or create papers
        $papers = Paper::limit(3)->get();

        if ($papers->count() === 0) {
            $papers = Paper::factory(3)->create();
        }

        // Create multiple Kangourou sessions per teacher
        foreach ($teachers as $teacher) {
            foreach ($papers as $paper) {
                // Create session with various statuses and expiration times
                $session = KangourouSession::factory()
                    ->withAuthor($teacher)
                    ->create([
                        'paper_id' => $paper->id,
                        'status' => fake()->randomElement(['draft', 'active']),
                        'privacy' => fake()->randomElement(['public', 'private']),
                    ]);

                // Create multiple attempts for this session with different statuses
                foreach ($students->random(min(5, $students->count())) as $student) {
                    $createdAt = fake()->randomElement([
                        now()->subDays(fake()->numberBetween(1, 30)),
                        now(),
                    ]);

                    Attempt::factory()
                        ->withUser($student)
                        ->create([
                            'kangourou_session_id' => $session->id,
                            'status' => fake()->randomElement(['inProgress', 'finished']),
                            'score' => fake()->randomElement([null, fake()->numberBetween(10, 130)]),
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                        ]);
                }
            }
        }
    }
}
