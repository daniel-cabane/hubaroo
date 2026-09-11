<?php

use App\Events\JumpAttemptUpdated;
use App\Jobs\AnalyseJump;
use App\Jobs\ExpireJumps;
use App\Models\Course;
use App\Models\Division;
use App\Models\Jump;
use App\Models\JumpAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

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
        ->and($attempt->fresh()->question_list[0]['answer'])->toBe('B')
        ->and($attempt->fresh()->question_list[0]['status'])->toBe('pending')
        ->and($attempt->fresh()->score)->toBe(0);
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

test('open jump already finished submit is still forbidden', function () {
    $attempt = JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => 1, 'status' => 'pending', 'answer' => 'A'],
        ],
        'score' => 0,
        'status' => 'finished',
        'timer' => 0,
        'extra_time' => 0,
        'termination' => 'submitted',
    ]);

    $this->actingAs($this->student)->postJson("/api/jump-attempts/{$attempt->id}/submit", [
        'question_list' => [
            ['id' => 1, 'status' => 'pending', 'answer' => 'B'],
        ],
    ])->assertForbidden()
        ->assertJsonPath('message', 'Already submitted.');

    expect($attempt->fresh()->question_list[0]['answer'])->toBe('A');
});

test('student cannot start an attempt after the jump expiration has passed', function () {
    $this->jump->update(['expiration' => now()->subMinute()]);

    $this->actingAs($this->student)->postJson("/api/jumps/{$this->jump->id}/attempts")
        ->assertForbidden();
});

test('isClosed is false for an active jump with a future expiration', function () {
    expect($this->jump->isClosed())->toBeFalse()
        ->and($this->jump->allowsLateAnswerSave())->toBeFalse();
});

test('active jump with expiration one minute past is closed and allows late save', function () {
    $this->jump->update(['expiration' => now()->subMinute()]);

    expect($this->jump->fresh()->isClosed())->toBeTrue()
        ->and($this->jump->fresh()->allowsLateAnswerSave())->toBeTrue();
});

test('expiring jump allows late save even when expiration is ten minutes past', function () {
    $jump = Jump::factory()->create([
        'course_id' => $this->course->id,
        'status' => 'expiring',
        'expiration' => now()->subMinutes(10),
    ]);

    expect($jump->isClosed())->toBeTrue()
        ->and($jump->allowsLateAnswerSave())->toBeTrue();
});

test('expired jump one minute past expiration allows late save', function () {
    $jump = Jump::factory()->create([
        'course_id' => $this->course->id,
        'status' => 'expired',
        'expiration' => now()->subMinute(),
    ]);

    expect($jump->isClosed())->toBeTrue()
        ->and($jump->allowsLateAnswerSave())->toBeTrue();
});

test('expired jump five minutes past expiration does not allow late save', function () {
    $jump = Jump::factory()->expired()->create(['course_id' => $this->course->id]);

    expect($jump->isClosed())->toBeTrue()
        ->and($jump->allowsLateAnswerSave())->toBeFalse();
});

test('closed expiring in-progress submit persists question list without grading', function () {
    Queue::fake();
    $jump = Jump::factory()->expiring()->create(['course_id' => $this->course->id, 'nb_questions' => 1]);
    $question = Question::first();
    $attempt = JumpAttempt::create([
        'jump_id' => $jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => $question->id, 'status' => 'pending', 'answer' => null, 'difficulty' => $question->difficulty],
        ],
        'score' => 0,
        'status' => 'inProgress',
        'timer' => 40,
        'extra_time' => 0,
        'termination' => 'none',
    ]);

    $response = $this->actingAs($this->student)->postJson("/api/jump-attempts/{$attempt->id}/submit", [
        'timer' => 20,
        'termination' => 'timeout',
        'question_list' => [
            ['id' => $question->id, 'status' => 'pending', 'answer' => 'A', 'difficulty' => $question->difficulty],
        ],
    ]);

    $response->assertOk()->assertJsonStructure(['attempt']);
    $fresh = $attempt->fresh();
    expect($fresh->status)->toBe('finished')
        ->and($fresh->termination)->toBe('timeout')
        ->and($fresh->question_list[0]['answer'])->toBe('A')
        ->and($fresh->question_list[0]['status'])->toBe('pending')
        ->and($fresh->score)->toBe(0);
    Queue::assertNotPushed(AnalyseJump::class);
});

test('expired timeout-finished submit overwrites question list inside the window without a second mastery pass', function () {
    Queue::fake();
    $question = Question::factory()->create(['correct_answer' => 'A', 'difficulty' => 1000]);
    $jump = Jump::factory()->create([
        'course_id' => $this->course->id,
        'status' => 'expired',
        'expiration' => now()->subMinute(),
        'nb_questions' => 1,
    ]);
    $this->student->update(['mastery' => 500]);
    $attempt = JumpAttempt::create([
        'jump_id' => $jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => $question->id, 'status' => 'incorrect', 'answer' => 'B', 'difficulty' => 1000],
        ],
        'score' => 0,
        'status' => 'finished',
        'timer' => 0,
        'extra_time' => 0,
        'termination' => 'timeout',
    ]);

    $response = $this->actingAs($this->student)->postJson("/api/jump-attempts/{$attempt->id}/submit", [
        'termination' => 'timeout',
        'question_list' => [
            ['id' => $question->id, 'status' => 'pending', 'answer' => 'A', 'difficulty' => 1000],
        ],
    ]);

    $response->assertOk();
    $fresh = $attempt->fresh();
    expect($fresh->question_list[0]['answer'])->toBe('A')
        ->and($fresh->question_list[0]['status'])->toBe('correct')
        ->and($fresh->score)->toBe(1000)
        ->and($this->student->fresh()->mastery)->toBe(500);
    Queue::assertPushed(AnalyseJump::class, fn (AnalyseJump $job) => $job->jump->is($jump));
});

test('late submit after ExpireJumps grades new answers without applying mastery twice', function () {
    Queue::fake();
    $question = Question::factory()->create(['correct_answer' => 'A', 'difficulty' => 1000]);
    $jump = Jump::factory()->expiring()->create([
        'course_id' => $this->course->id,
        'expiration' => now()->subMinute(),
        'nb_questions' => 1,
    ]);
    $this->student->update(['mastery' => 1500]);
    $attempt = JumpAttempt::create([
        'jump_id' => $jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => $question->id, 'status' => 'pending', 'answer' => 'B', 'difficulty' => 1000],
        ],
        'score' => 0,
        'status' => 'inProgress',
        'timer' => 40,
        'extra_time' => 0,
        'termination' => 'none',
    ]);

    (new ExpireJumps)->handle();

    $masteryAfterJob = $this->student->fresh()->mastery;

    expect($attempt->fresh()->termination)->toBe('timeout')
        ->and($masteryAfterJob)->toBeLessThan(1500);

    $this->actingAs($this->student)->postJson("/api/jump-attempts/{$attempt->id}/submit", [
        'termination' => 'timeout',
        'question_list' => [
            ['id' => $question->id, 'status' => 'pending', 'answer' => 'A', 'difficulty' => 1000],
        ],
    ])->assertOk();

    expect($attempt->fresh()->score)->toBe(1000)
        ->and($this->student->fresh()->mastery)->toBe($masteryAfterJob);
});

test('expired already-finished submit ignores question list outside the window', function () {
    $jump = Jump::factory()->expired()->create(['course_id' => $this->course->id, 'nb_questions' => 1]);
    $attempt = JumpAttempt::create([
        'jump_id' => $jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => 1, 'status' => 'incorrect', 'answer' => 'A', 'difficulty' => 1000],
        ],
        'score' => 0,
        'status' => 'finished',
        'timer' => 0,
        'extra_time' => 0,
        'termination' => 'timeout',
    ]);

    $this->actingAs($this->student)->postJson("/api/jump-attempts/{$attempt->id}/submit", [
        'question_list' => [
            ['id' => 1, 'status' => 'pending', 'answer' => 'B', 'difficulty' => 1000],
        ],
    ])->assertOk()->assertJsonStructure(['attempt']);

    expect($attempt->fresh()->question_list[0]['answer'])->toBe('A')
        ->and($attempt->fresh()->score)->toBe(0);
});

test('expired already-finished submit without question list returns success unchanged', function () {
    $jump = Jump::factory()->create([
        'course_id' => $this->course->id,
        'status' => 'expired',
        'expiration' => now()->subMinute(),
        'nb_questions' => 1,
    ]);
    $attempt = JumpAttempt::create([
        'jump_id' => $jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => 1, 'status' => 'correct', 'answer' => 'A', 'difficulty' => 1000],
        ],
        'score' => 1000,
        'status' => 'finished',
        'timer' => 12,
        'extra_time' => 0,
        'termination' => 'timeout',
    ]);

    $this->actingAs($this->student)->postJson("/api/jump-attempts/{$attempt->id}/submit")
        ->assertOk()
        ->assertJsonStructure(['attempt']);

    expect($attempt->fresh()->question_list[0]['answer'])->toBe('A')
        ->and($attempt->fresh()->score)->toBe(1000)
        ->and($attempt->fresh()->timer)->toBe(12);
});

test('sync while jump is closed and writable persists and returns jump_closed', function () {
    $jump = Jump::factory()->expiring()->create(['course_id' => $this->course->id, 'nb_questions' => 1]);
    $attempt = JumpAttempt::create([
        'jump_id' => $jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => 1, 'status' => 'pending', 'answer' => null],
        ],
        'score' => 0,
        'status' => 'inProgress',
        'timer' => 30,
        'extra_time' => 0,
        'termination' => 'none',
    ]);

    $this->actingAs($this->student)->patchJson("/api/jump-attempts/{$attempt->id}/sync", [
        'timer' => 15,
        'changes' => [
            ['question_index' => 0, 'answer' => 'C'],
        ],
    ])->assertOk()->assertJson([
        'jump_closed' => true,
        'saved' => true,
    ]);

    $fresh = $attempt->fresh();
    expect($fresh->question_list[0]['answer'])->toBe('C')
        ->and($fresh->timer)->toBe(15);
    Event::assertDispatched(JumpAttemptUpdated::class);
});

test('sync while jump is closed and not writable does not persist', function () {
    $jump = Jump::factory()->expired()->create(['course_id' => $this->course->id, 'nb_questions' => 1]);
    $attempt = JumpAttempt::create([
        'jump_id' => $jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => 1, 'status' => 'incorrect', 'answer' => 'A'],
        ],
        'score' => 0,
        'status' => 'finished',
        'timer' => 0,
        'extra_time' => 0,
        'termination' => 'timeout',
    ]);

    $this->actingAs($this->student)->patchJson("/api/jump-attempts/{$attempt->id}/sync", [
        'timer' => 5,
        'changes' => [
            ['question_index' => 0, 'answer' => 'B'],
        ],
    ])->assertOk()->assertJson([
        'jump_closed' => true,
        'saved' => false,
    ]);

    expect($attempt->fresh()->question_list[0]['answer'])->toBe('A');
    Event::assertNotDispatched(JumpAttemptUpdated::class);
});

test('sync of a finished timeout attempt is allowed while the jump is closed and writable', function () {
    $jump = Jump::factory()->create([
        'course_id' => $this->course->id,
        'status' => 'expired',
        'expiration' => now()->subMinute(),
        'nb_questions' => 1,
    ]);
    $attempt = JumpAttempt::create([
        'jump_id' => $jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => 1, 'status' => 'incorrect', 'answer' => 'A'],
        ],
        'score' => 0,
        'status' => 'finished',
        'timer' => 0,
        'extra_time' => 0,
        'termination' => 'timeout',
    ]);

    $this->actingAs($this->student)->patchJson("/api/jump-attempts/{$attempt->id}/sync", [
        'changes' => [
            ['question_index' => 0, 'answer' => 'B'],
        ],
    ])->assertOk()->assertJson([
        'jump_closed' => true,
        'saved' => true,
    ]);

    expect($attempt->fresh()->question_list[0]['answer'])->toBe('B');
});

test('another user cannot submit after the jump is closed', function () {
    $other = User::factory()->create();
    $jump = Jump::factory()->expiring()->create(['course_id' => $this->course->id, 'nb_questions' => 1]);
    $attempt = JumpAttempt::create([
        'jump_id' => $jump->id,
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

    $this->actingAs($other)->postJson("/api/jump-attempts/{$attempt->id}/submit", [
        'question_list' => [
            ['id' => 1, 'status' => 'pending', 'answer' => 'A'],
        ],
    ])->assertForbidden();

    expect($attempt->fresh()->status)->toBe('inProgress');
});
