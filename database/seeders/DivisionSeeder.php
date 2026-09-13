<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Division;
use App\Models\Jump;
use App\Models\JumpAttempt;
use App\Models\Question;
use App\Models\User;
use App\Services\JumpGradingService;
use App\Services\JumpQuestionSelector;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DivisionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teachers = User::role('Teacher')->get();
        $students = User::role('Student')->get();

        if ($teachers->isEmpty() || $students->isEmpty()) {
            return;
        }

        if (Question::query()->doesntExist()) {
            Question::factory(120)->create();
        }

        $this->ensureQuestionDifficulties();

        $questionSelector = app(JumpQuestionSelector::class);
        $gradingService = app(JumpGradingService::class);
        $correctAnswers = Question::query()->pluck('correct_answer', 'id');

        foreach ($teachers as $teacher) {
            Division::factory(2)->create([
                'teacher_id' => $teacher->id,
            ])->each(function (Division $division) use ($students, $questionSelector, $gradingService, $correctAnswers): void {
                $studentCount = random_int(5, 10);
                $divisionStudents = $students->random(min($studentCount, $students->count()));
                $division->students()->attach($divisionStudents->pluck('id'));

                $this->seedCourses(
                    $division,
                    $divisionStudents,
                    $questionSelector,
                    $gradingService,
                    $correctAnswers,
                );
            });
        }
    }

    /**
     * @param  Collection<int, User>  $divisionStudents
     * @param  Collection<int, string>  $correctAnswers
     */
    private function seedCourses(
        Division $division,
        Collection $divisionStudents,
        JumpQuestionSelector $questionSelector,
        JumpGradingService $gradingService,
        Collection $correctAnswers,
    ): void {
        $answeringStudents = $this->answeringStudents($divisionStudents);

        Course::factory(random_int(1, 3))
            ->create(['division_id' => $division->id])
            ->each(function (Course $course) use ($division, $answeringStudents, $questionSelector, $gradingService, $correctAnswers): void {
                Jump::factory(random_int(3, 5))
                    ->expired()
                    ->create(['course_id' => $course->id])
                    ->each(function (Jump $jump) use ($division, $answeringStudents, $questionSelector, $gradingService, $correctAnswers): void {
                        $this->seedJumpAnswers(
                            $jump,
                            $division,
                            $answeringStudents,
                            $questionSelector,
                            $gradingService,
                            $correctAnswers,
                        );
                    });
            });
    }

    /**
     * @param  Collection<int, User>  $students
     * @return Collection<int, User>
     */
    private function answeringStudents(Collection $students): Collection
    {
        $count = (int) round($students->count() * 0.8);

        if ($count < 1) {
            return collect();
        }

        return $students->random($count)->values();
    }

    private function ensureQuestionDifficulties(): void
    {
        Question::query()
            ->whereNull('difficulty')
            ->with('papers')
            ->chunkById(100, function (Collection $questions): void {
                foreach ($questions as $question) {
                    $level = $question->papers
                        ->map(fn ($paper): int => Question::LEVEL_VALUES[$paper->level] ?? 1)
                        ->max() ?? 1;

                    $question->update([
                        'difficulty' => Question::calculateDifficulty((int) $question->tier, (int) $level),
                    ]);
                }
            });
    }

    /**
     * @param  Collection<int, User>  $students
     * @param  Collection<int, string>  $correctAnswers
     */
    private function seedJumpAnswers(
        Jump $jump,
        Division $division,
        Collection $students,
        JumpQuestionSelector $questionSelector,
        JumpGradingService $gradingService,
        Collection $correctAnswers,
    ): void {
        foreach ($students as $student) {
            $questionList = collect($questionSelector->selectQuestions($jump, $student, $division))
                ->map(function (array $item) use ($correctAnswers): array {
                    $correct = $correctAnswers->get($item['id']);
                    $item['answer'] = fake()->boolean(65) && is_string($correct)
                        ? $correct
                        : fake()->randomElement(['A', 'B', 'C', 'D', 'E']);

                    return $item;
                })
                ->all();

            if ($questionList === []) {
                continue;
            }

            $attempt = JumpAttempt::create([
                'jump_id' => $jump->id,
                'user_id' => $student->id,
                'question_list' => $questionList,
                'score' => 0,
                'status' => 'finished',
                'timer' => random_int(0, $jump->time * 60),
                'extra_time' => 0,
                'termination' => 'submitted',
            ]);

            $gradingService->gradeAttempt($attempt, $correctAnswers);
        }
    }
}
