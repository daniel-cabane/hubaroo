<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // Create admin user
        $admin = User::factory()->withSecretPassword()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
        $admin->assignRole('Admin');

        // Seed teachers and students if in development
        if (app()->environment('local')) {
            // Create 3 teachers
            User::factory(3)
                ->withSecretPassword()
                ->create()
                ->each(fn (User $user) => $user->assignRole('Teacher'));

            // Create 20 students
            User::factory(20)
                ->withSecretPassword()
                ->create()
                ->each(fn (User $user) => $user->assignRole('Student'));
        }
    }
}
