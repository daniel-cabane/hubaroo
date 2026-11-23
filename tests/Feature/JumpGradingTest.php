<?php

use App\Models\Course;
use App\Models\Division;
use App\Models\Jump;
use App\Models\JumpAttempt;
use App\Models\Question;
use App\Models\User;
use App\Services\JumpGradingService;

beforeEach(function () {
    $this->teacher = User::factory()->create();
    $this->division = Division::factory()->create(['teacher_id' => $this->teacher->id]);
    $this->student = User::factory()->create();
    $this->division->students()->attach($this->student->id);
    $this->course = Course::factory()->create(['division_id' => $this->division->id]);
    $this->grading = app(JumpGradingService::class);
});

test('jump points follow the difficulty table', function () {
    $cases = [
        [0, 0, -1],
        [400, 4, -1],
        [450, 5, -1],
        [1000, 10, -1],
        [1499, 15, -1],
        [1500, 15, -2],
        [2300, 23, -2],
    ];

    foreach ($cases as [$difficulty, $right, $wrong]) {
        expect($this->grading->pointsFor($difficulty, 'correct'))->toBe($right)
            ->and($this->grading->pointsFor($difficulty, 'incorrect'))->toBe($wrong);
    }

    expect($this->grading->pointsFor(-80, 'correct'))->toBe(0)
        ->and($this->grading->pointsFor(-80, 'incorrect'))->toBe(-1);
});

test('grading stores signed points and floors the total at zero', function () {
    $question = Question::factory()->create(['correct_answer' => 'A', 'difficulty' => 2300]);
    $jump = Jump::factory()->expired()->create(['course_id' => $this->course->id, 'nb_questions' => 3]);
    $attempt = JumpAttempt::create([
        'jump_id' => $jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => $question->id, 'answer' => 'A', 'status' => 'pending', 'difficulty' => 1000, 'points' => 999],
            ['id' => $question->id, 'answer' => 'B', 'status' => 'pending', 'difficulty' => 1000],
            ['id' => $question->id, 'answer' => 'B', 'status' => 'pending', 'difficulty' => 1000],
        ],
        'score' => 0,
        'status' => 'finished',
        'termination' => 'submitted',
    ]);

    $this->grading->gradeAttempt($attempt);

    $fresh = $attempt->fresh();
    expect($fresh->question_list[0]['status'])->toBe('correct')
        ->and($fresh->question_list[0]['points'])->toBe(10)
        ->and($fresh->question_list[1]['points'])->toBe(-1)
        ->and($fresh->question_list[2]['points'])->toBe(-1)
        ->and($fresh->score)->toBe(8)
        ->and($question->fresh()->difficulty)->toBe(2300);
});

test('a blank answer scores zero and stays incorrect', function (mixed $answer) {
    $question = Question::factory()->create(['correct_answer' => 'A', 'difficulty' => 1000]);
    $jump = Jump::factory()->expired()->create(['course_id' => $this->course->id, 'nb_questions' => 1]);
    $attempt = JumpAttempt::create([
        'jump_id' => $jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => $question->id, 'answer' => $answer, 'status' => 'pending', 'difficulty' => 1000],
        ],
        'score' => 0,
        'status' => 'finished',
        'termination' => 'submitted',
    ]);

    $this->grading->gradeAttempt($attempt);

    $item = $attempt->fresh()->question_list[0];
    expect($item['status'])->toBe('incorrect')
        ->and($item['points'])->toBe(0)
        ->and($attempt->fresh()->score)->toBe(0);
})->with([
    'null' => null,
    'empty string' => '',
]);

test('three wrong answers floor the score at zero and keep the penalties', function () {
    $question = Question::factory()->create(['correct_answer' => 'A', 'difficulty' => 1000]);
    $jump = Jump::factory()->expired()->create(['course_id' => $this->course->id, 'nb_questions' => 3]);
    $attempt = JumpAttempt::create([
        'jump_id' => $jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => $question->id, 'answer' => 'B', 'status' => 'pending', 'difficulty' => 1000],
            ['id' => $question->id, 'answer' => 'C', 'status' => 'pending', 'difficulty' => 1000],
            ['id' => $question->id, 'answer' => 'D', 'status' => 'pending', 'difficulty' => 1500],
        ],
        'score' => 0,
        'status' => 'finished',
        'termination' => 'submitted',
    ]);

    $this->grading->gradeAttempt($attempt);

    $fresh = $attempt->fresh();
    expect($fresh->question_list[0]['points'])->toBe(-1)
        ->and($fresh->question_list[1]['points'])->toBe(-1)
        ->and($fresh->question_list[2]['points'])->toBe(-2)
        ->and($fresh->score)->toBe(0);
});

test('grading uses the snapshotted difficulty when the question difficulty has changed', function () {
    $question = Question::factory()->create(['correct_answer' => 'A', 'difficulty' => 2300]);
    $jump = Jump::factory()->expired()->create(['course_id' => $this->course->id, 'nb_questions' => 1]);
    $attempt = JumpAttempt::create([
        'jump_id' => $jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => $question->id, 'answer' => 'A', 'status' => 'pending', 'difficulty' => 400],
        ],
        'score' => 0,
        'status' => 'finished',
        'termination' => 'submitted',
    ]);

    $this->grading->gradeAttempt($attempt);

    expect($attempt->fresh()->question_list[0]['points'])->toBe(4)
        ->and($attempt->fresh()->score)->toBe(4)
        ->and($question->fresh()->difficulty)->toBe(2300);
});

test('a wrong letter with no correct answer takes the penalty', function () {
    $jump = Jump::factory()->expired()->create(['course_id' => $this->course->id, 'nb_questions' => 1]);
    $attempt = JumpAttempt::create([
        'jump_id' => $jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => 999999, 'answer' => 'B', 'status' => 'pending', 'difficulty' => 1000],
        ],
        'score' => 0,
        'status' => 'finished',
        'termination' => 'submitted',
    ]);

    $this->grading->gradeAttempt($attempt, collect());

    expect($attempt->fresh()->question_list[0]['status'])->toBe('incorrect')
        ->and($attempt->fresh()->question_list[0]['points'])->toBe(-1)
        ->and($attempt->fresh()->score)->toBe(0);
});

test('an expired correction computes points for a legacy attempt without storing them', function () {
    $question = Question::factory()->create(['correct_answer' => 'C', 'difficulty' => 1000]);
    $jump = Jump::factory()->expired()->create(['course_id' => $this->course->id, 'nb_questions' => 2]);
    $attempt = JumpAttempt::create([
        'jump_id' => $jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => $question->id, 'answer' => 'B', 'status' => 'incorrect', 'difficulty' => 1000],
            ['id' => $question->id, 'answer' => null, 'status' => 'incorrect', 'difficulty' => 1000],
        ],
        'score' => 1000,
        'status' => 'finished',
        'termination' => 'submitted',
    ]);

    $response = $this->actingAs($this->student)->getJson("/api/jump-attempts/{$attempt->id}");

    $response->assertOk();
    expect($response->json('attempt.score'))->toBe(0)
        ->and($response->json('attempt.question_list.0.points'))->toBe(-1)
        ->and($response->json('attempt.question_list.1.points'))->toBe(0)
        ->and($response->json('attempt.question_list.0.correct_answer'))->toBe('C');

    $stored = $attempt->fresh();
    expect($stored->score)->toBe(1000)
        ->and($stored->question_list[0])->not->toHaveKey('points');
});

test('points stay off the student payload until the jump is expired', function () {
    $question = Question::factory()->create(['correct_answer' => 'A', 'difficulty' => 1000]);
    $jump = Jump::factory()->active()->create(['course_id' => $this->course->id, 'nb_questions' => 1]);
    $attempt = JumpAttempt::create([
        'jump_id' => $jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => $question->id, 'answer' => 'A', 'status' => 'pending', 'difficulty' => 1000, 'points' => 10, 'correct_answer' => 'A'],
        ],
        'score' => 0,
        'status' => 'inProgress',
        'termination' => 'none',
    ]);

    $response = $this->actingAs($this->student)->getJson("/api/jump-attempts/{$attempt->id}");

    $response->assertOk()
        ->assertJsonMissingPath('attempt.question_list.0.points')
        ->assertJsonMissingPath('attempt.question_list.0.correct_answer');

    expect($response->json('attempt.score'))->toBe(0)
        ->and($attempt->fresh()->question_list[0]['points'])->toBe(10);
});
