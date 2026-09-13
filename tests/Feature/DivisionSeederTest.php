<?php

use App\Models\Division;
use App\Models\JumpAttempt;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\DivisionSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'web']);

    User::factory()->create()->assignRole('Teacher');
    User::factory(8)->create()->each(fn (User $user) => $user->assignRole('Student'));
    Question::factory(200)->create(['difficulty' => null]);
});

test('division seeder creates courses, jumps, and answers for eighty percent of students', function () {
    $this->seed(DivisionSeeder::class);

    $divisions = Division::query()->with(['courses.jumps.attempts', 'students'])->get();

    expect($divisions)->toHaveCount(2);

    foreach ($divisions as $division) {
        expect($division->students->count())->toBeGreaterThanOrEqual(5)
            ->and($division->students->count())->toBeLessThanOrEqual(8)
            ->and($division->courses->count())->toBeGreaterThanOrEqual(1)
            ->and($division->courses->count())->toBeLessThanOrEqual(3);

        $expectedAnswers = (int) round($division->students->count() * 0.8);

        foreach ($division->courses as $course) {
            expect($course->jumps->count())->toBeGreaterThanOrEqual(3)
                ->and($course->jumps->count())->toBeLessThanOrEqual(5);

            foreach ($course->jumps as $jump) {
                expect($jump->status)->toBe('expired')
                    ->and($jump->attempts)->toHaveCount($expectedAnswers);

                $jump->attempts->each(function (JumpAttempt $attempt): void {
                    expect($attempt->status)->toBe('finished')
                        ->and($attempt->termination)->toBe('submitted')
                        ->and($attempt->question_list)->not->toBeEmpty();

                    $expectedScore = 0;

                    foreach ($attempt->question_list as $item) {
                        expect($item)->toHaveKey('answer')
                            ->and($item['answer'])->not->toBeNull()
                            ->and($item['status'])->toBeIn(['correct', 'incorrect'])
                            ->and((int) ($item['difficulty'] ?? 0))->toBeGreaterThan(0);

                        if ($item['status'] === 'correct') {
                            $expectedScore += (int) $item['difficulty'];
                        }
                    }

                    expect($attempt->score)->toBe($expectedScore);
                });
            }
        }
    }

    expect(JumpAttempt::query()->where('score', '>', 0)->exists())->toBeTrue();
});
