<?php

use App\Events\JumpAttemptUpdated;
use App\Models\Course;
use App\Models\Division;
use App\Models\Jump;
use App\Models\JumpAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();
    $this->teacher = User::factory()->create();
    $this->division = Division::factory()->create(['teacher_id' => $this->teacher->id]);
    $this->student = User::factory()->create();
    $this->division->students()->attach($this->student->id);
    $this->course = Course::factory()->create(['division_id' => $this->division->id]);
    $this->jump = Jump::factory()->active()->create(['course_id' => $this->course->id, 'nb_questions' => 3]);

    // Create questions for the selector
    Question::factory()->count(10)->create(['difficulty' => 1000]);
});

test('student can start a jump attempt', function () {
    $response = $this->actingAs($this->student)->postJson("/api/jumps/{$this->jump->id}/attempts");

    $response->assertCreated()->assertJsonStructure(['attempt' => ['id', 'question_list', 'status']]);
    $attempt = JumpAttempt::where('jump_id', $this->jump->id)->where('user_id', $this->student->id)->first();

    expect($attempt)->not->toBeNull()
        ->and($attempt->timer)->toBe($this->jump->time * 60);

    Event::assertDispatched(JumpAttemptUpdated::class, function (JumpAttemptUpdated $event) {
        $payload = $event->broadcastWith()['attempt'];

        return $payload['user_id'] === $this->student->id
            && $payload['status'] === 'inProgress'
            && $payload['timer'] === $this->jump->time * 60
            && $payload['answered_count'] === 0
            && $payload['total_questions'] === count($event->jumpAttempt->question_list)
            && ! array_key_exists('question_list', $payload);
    });
});

test('student not in division cannot start attempt', function () {
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->postJson("/api/jumps/{$this->jump->id}/attempts");

    $response->assertForbidden();
});

test('student cannot start attempt for inactive jump', function () {
    $draftJump = Jump::factory()->create(['course_id' => $this->course->id, 'status' => 'draft']);

    $response = $this->actingAs($this->student)->postJson("/api/jumps/{$draftJump->id}/attempts");

    $response->assertForbidden();
});

test('student gets 409 with requires_rejoin when attempt already exists', function () {
    JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [],
        'score' => 0,
        'status' => 'inProgress',
        'timer' => 0,
        'extra_time' => 0,
        'termination' => 'none',
    ]);

    $response = $this->actingAs($this->student)->postJson("/api/jumps/{$this->jump->id}/attempts");

    $response->assertStatus(409)->assertJsonPath('requires_rejoin', true);
});

test('409 response includes attempt status so client can route correctly', function () {
    JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [],
        'score' => 0,
        'status' => 'inProgress',
        'timer' => 0,
        'extra_time' => 0,
        'termination' => 'none',
    ]);

    $response = $this->actingAs($this->student)->postJson("/api/jumps/{$this->jump->id}/attempts");

    $response->assertStatus(409)->assertJsonPath('attempt.status', 'inProgress');
});

test('student can update an answer', function () {
    $attempt = JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => 1, 'status' => 'pending', 'answer' => null],
        ],
        'score' => 0,
        'status' => 'inProgress',
        'timer' => 0,
        'extra_time' => 0,
        'termination' => 'none',
    ]);

    $response = $this->actingAs($this->student)->patchJson("/api/jump-attempts/{$attempt->id}/answer", [
        'question_index' => 0,
        'answer' => 'A',
        'timer' => 30,
    ]);

    $response->assertNoContent();
    expect($attempt->fresh()->question_list[0]['answer'])->toBe('A');
});

test('student can sync multiple answers in one request', function () {
    $attempt = JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => 1, 'status' => 'pending', 'answer' => null],
            ['id' => 2, 'status' => 'pending', 'answer' => null],
            ['id' => 3, 'status' => 'pending', 'answer' => null],
        ],
        'score' => 0,
        'status' => 'inProgress',
        'timer' => 0,
        'extra_time' => 0,
        'termination' => 'none',
    ]);

    $response = $this->actingAs($this->student)->patchJson("/api/jump-attempts/{$attempt->id}/sync", [
        'timer' => 45,
        'changes' => [
            ['question_index' => 0, 'answer' => 'A'],
            ['question_index' => 2, 'answer' => 'C'],
        ],
    ]);

    $response->assertNoContent();

    $fresh = $attempt->fresh();

    expect($fresh->timer)->toBe(45)
        ->and($fresh->question_list[0]['answer'])->toBe('A')
        ->and($fresh->question_list[2]['answer'])->toBe('C');

    Event::assertDispatched(JumpAttemptUpdated::class, function (JumpAttemptUpdated $event) use ($attempt) {
        $payload = $event->broadcastWith()['attempt'];

        return $event->jumpAttempt->is($attempt->fresh())
            && $payload['timer'] === 45
            && $payload['answered_count'] === 2
            && $payload['total_questions'] === 3
            && $payload['score'] === null
            && is_string($payload['updated_at'] ?? null)
            && ! array_key_exists('question_list', $payload);
    });
});

test('sync rejects invalid question indices', function () {
    $attempt = JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => 1, 'status' => 'pending', 'answer' => null],
        ],
        'score' => 0,
        'status' => 'inProgress',
        'timer' => 0,
        'extra_time' => 0,
        'termination' => 'none',
    ]);

    $response = $this->actingAs($this->student)->patchJson("/api/jump-attempts/{$attempt->id}/sync", [
        'timer' => 15,
        'changes' => [
            ['question_index' => 2, 'answer' => 'A'],
        ],
    ]);

    $response->assertStatus(422);
    Event::assertNotDispatched(JumpAttemptUpdated::class);
});

test('sync rejects unauthorized users', function () {
    $otherStudent = User::factory()->create();
    $attempt = JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => 1, 'status' => 'pending', 'answer' => null],
        ],
        'score' => 0,
        'status' => 'inProgress',
        'timer' => 0,
        'extra_time' => 0,
        'termination' => 'none',
    ]);

    $response = $this->actingAs($otherStudent)->patchJson("/api/jump-attempts/{$attempt->id}/sync", [
        'timer' => 15,
        'changes' => [
            ['question_index' => 0, 'answer' => 'A'],
        ],
    ]);

    $response->assertForbidden();
    Event::assertNotDispatched(JumpAttemptUpdated::class);
});

test('sync rejects finished attempts', function () {
    $attempt = JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => 1, 'status' => 'pending', 'answer' => null],
        ],
        'score' => 0,
        'status' => 'finished',
        'timer' => 0,
        'extra_time' => 0,
        'termination' => 'submitted',
    ]);

    $response = $this->actingAs($this->student)->patchJson("/api/jump-attempts/{$attempt->id}/sync", [
        'timer' => 15,
        'changes' => [
            ['question_index' => 0, 'answer' => 'A'],
        ],
    ]);

    $response->assertForbidden();
    Event::assertNotDispatched(JumpAttemptUpdated::class);
});

test('student can submit attempt', function () {
    $attempt = JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => 1, 'status' => 'pending', 'answer' => null],
        ],
        'score' => 0,
        'status' => 'inProgress',
        'timer' => 100,
        'extra_time' => 0,
        'termination' => 'none',
    ]);

    $response = $this->actingAs($this->student)->postJson("/api/jump-attempts/{$attempt->id}/submit", [
        'timer' => 120,
        'termination' => 'submitted',
        'question_list' => [
            ['id' => 1, 'status' => 'pending', 'answer' => 'B'],
        ],
    ]);

    $response->assertOk();
    expect($attempt->fresh()->status)->toBe('finished')
        ->and($attempt->fresh()->question_list[0]['answer'])->toBe('B');
    Event::assertDispatched(JumpAttemptUpdated::class, function (JumpAttemptUpdated $event) {
        $payload = $event->broadcastWith()['attempt'];

        return $payload['status'] === 'finished'
            && $payload['termination'] === 'submitted'
            && $payload['answered_count'] === 1
            && $payload['score'] === 0;
    });
});

test('student can fetch their own attempt', function () {
    $attempt = JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [],
        'score' => 0,
        'status' => 'inProgress',
        'timer' => 0,
        'extra_time' => 0,
        'termination' => 'none',
    ]);

    $response = $this->actingAs($this->student)->getJson("/api/jump-attempts/{$attempt->id}");

    $response->assertOk()->assertJsonStructure(['attempt']);
});

test('authenticated user can fetch their jump attempts', function () {
    JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [],
        'score' => 0,
        'status' => 'inProgress',
        'timer' => 0,
        'extra_time' => 0,
        'termination' => 'none',
    ]);

    $response = $this->actingAs($this->student)->getJson('/api/my/jump-attempts');

    $response->assertOk()
        ->assertJsonStructure(['attempts' => [['id', 'jump_id', 'user_id', 'status', 'updated_at']]])
        ->assertJsonCount(1, 'attempts');
});

test('guest cannot fetch my jump attempts', function () {
    $this->getJson('/api/my/jump-attempts')->assertUnauthorized();
});
