<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DivisionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teachers = User::role('Teacher')->get();
        $students = User::role('Student')->get();

        foreach ($teachers as $teacher) {
            // Create 2 divisions for each teacher
            Division::factory(2)->create([
                'teacher_id' => $teacher->id,
            ])->each(function (Division $division) use ($students) {
                // Attach 5-10 random students to each division
                $studentCount = random_int(5, 10);
                $randomStudents = $students->random(min($studentCount, $students->count()));
                $division->students()->attach($randomStudents->pluck('id'));
            });
        }
    }
}
