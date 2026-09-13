<?php

use App\Events\JumpAttemptUpdated;
use App\Models\Course;
use App\Models\Division;
use App\Models\Jump;
use App\Models\JumpAttempt;
use App\Models\Question;
use App\Models\User;

beforeEach(function () {
    $this->teacher = User::factory()->create();
    $this->division = Division::factory()->create(['teacher_id' => $this->teacher->id]);
    $this->student = User::factory()->create();
    $this->division->students()->attach($this->student->id);
    $this->course = Course::factory()->create(['division_id' => $this->division->id]);
    $this->jump = Jump::factory()->expired()->create([
        'course_id' => $this->course->id,
        'nb_questions' => 3,
    ]);
});

test('answer breakdown counts correct incorrect and unanswered questions', function () {
    $correct = Question::factory()->create(['correct_answer' => 'A']);
    $incorrect = Question::factory()->create(['correct_answer' => 'B']);
    $unanswered = Question::factory()->create(['correct_answer' => 'C']);

    $attempt = JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => $correct->id, 'answer' => 'A', 'status' => 'correct'],
            ['id' => $incorrect->id, 'answer' => 'A', 'status' => 'incorrect'],
            ['id' => $unanswered->id, 'answer' => null, 'status' => 'incorrect'],
        ],
        'score' => 0,
        'status' => 'finished',
        'timer' => 0,
        'extra_time' => 0,
        'termination' => 'submitted',
    ]);

    expect($attempt->answerBreakdown())->toMatchArray([
        'correct' => 1,
        'incorrect' => 1,
        'unanswered' => 1,
        'total' => 3,
    ]);
});

test('answer breakdown classifies pending answers using the correct answer', function () {
    $correct = Question::factory()->create(['correct_answer' => 'A']);
    $incorrect = Question::factory()->create(['correct_answer' => 'B']);
    $unanswered = Question::factory()->create(['correct_answer' => 'C']);

    $attempt = JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => $correct->id, 'answer' => 'A', 'status' => 'pending'],
            ['id' => $incorrect->id, 'answer' => 'A', 'status' => 'pending'],
            ['id' => $unanswered->id, 'answer' => null, 'status' => 'pending'],
        ],
        'score' => 0,
        'status' => 'inProgress',
        'timer' => 60,
        'extra_time' => 0,
        'termination' => 'none',
    ]);

    expect($attempt->answerBreakdown())->toMatchArray([
        'correct' => 1,
        'incorrect' => 1,
        'unanswered' => 1,
        'total' => 3,
    ]);
});

test('jump attempt updated broadcast includes correct incorrect and unanswered counts', function () {
    $correct = Question::factory()->create(['correct_answer' => 'A']);
    $incorrect = Question::factory()->create(['correct_answer' => 'B']);
    $unanswered = Question::factory()->create(['correct_answer' => 'C']);

    $attempt = JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => $correct->id, 'answer' => 'A', 'status' => 'pending'],
            ['id' => $incorrect->id, 'answer' => 'A', 'status' => 'pending'],
            ['id' => $unanswered->id, 'answer' => null, 'status' => 'pending'],
        ],
        'score' => 0,
        'status' => 'inProgress',
        'timer' => 60,
        'extra_time' => 0,
        'termination' => 'none',
    ]);

    $payload = (new JumpAttemptUpdated($attempt))->broadcastWith()['attempt'];

    expect($payload['answered_count'])->toBe(2)
        ->and($payload['correct_count'])->toBe(1)
        ->and($payload['incorrect_count'])->toBe(1)
        ->and($payload['unanswered_count'])->toBe(1)
        ->and($payload['total_questions'])->toBe(3)
        ->and($payload)->not->toHaveKey('question_list');
});

test('course details includes answer breakdown counts for teacher observation', function () {
    $correct = Question::factory()->create(['correct_answer' => 'A']);
    $incorrect = Question::factory()->create(['correct_answer' => 'B']);
    $unanswered = Question::factory()->create(['correct_answer' => 'C']);

    JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => $correct->id, 'answer' => 'A', 'status' => 'correct'],
            ['id' => $incorrect->id, 'answer' => 'A', 'status' => 'incorrect'],
            ['id' => $unanswered->id, 'answer' => null, 'status' => 'incorrect'],
        ],
        'score' => 1000,
        'status' => 'finished',
        'timer' => 40,
        'extra_time' => 0,
        'termination' => 'submitted',
    ]);

    $response = $this->actingAs($this->teacher)->getJson("/api/courses/{$this->course->id}/details");

    $response->assertOk();
    $jumpData = collect($response->json('jumps'))->firstWhere('id', $this->jump->id);
    $attempt = $jumpData['attempts'][0];

    expect($attempt['correct_count'])->toBe(1)
        ->and($attempt['incorrect_count'])->toBe(1)
        ->and($attempt['unanswered_count'])->toBe(1);
});
