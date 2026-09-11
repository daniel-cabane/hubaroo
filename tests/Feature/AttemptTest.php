<?php

use App\Events\AttemptUpdated;
use App\Jobs\ExpireKangourouSessions;
use App\Jobs\UpdateMasteryAndDifficulty;
use App\Models\Attempt;
use App\Models\Division;
use App\Models\KangourouSession;
use App\Models\Paper;
use App\Models\User;
use App\Services\GradingService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->paper = Paper::factory()->withQuestions()->create();
    $this->session = KangourouSession::factory()->create(['paper_id' => $this->paper->id]);
});

test('can create an attempt for an active session', function () {
    $response = $this->postJson("/api/kangourou-sessions/{$this->session->code}/attempts");

    $response->assertCreated();
    $response->assertJsonStructure(['message', 'attempt' => ['id', 'code', 'answers', 'status']]);
    expect($response->json('attempt.status'))->toBe('inProgress');
    expect(count($response->json('attempt.answers')))->toBe(26);
});

test('cannot create an attempt for an expired session', function () {
    $session = KangourouSession::factory()->expired()->create(['paper_id' => $this->paper->id]);

    $response = $this->postJson("/api/kangourou-sessions/{$session->code}/attempts");

    $response->assertForbidden();
});

test('cannot create an attempt for a draft session', function () {
    $session = KangourouSession::factory()->draft()->create(['paper_id' => $this->paper->id]);

    $response = $this->postJson("/api/kangourou-sessions/{$session->code}/attempts");

    $response->assertForbidden();
    expect($response->json('message'))->toBe("Cette session n'est pas encore disponible.");
});

test('attempt has a unique 6-char recovery code', function () {
    $code = Attempt::generateCode();

    expect(strlen($code))->toBe(6);
    expect($code)->toMatch('/^[A-Z0-9]+$/');
});

test('can update a single answer', function () {
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);

    $response = $this->patchJson("/api/attempts/{$attempt->id}/answer", [
        'question_index' => 0,
        'answer' => 'B',
    ]);

    $response->assertOk();
    expect($response->json('attempt.answers.0.answer'))->toBe('B');
    expect($response->json('attempt.answers.0.status'))->toBe('answered');
});

test('can clear an answer by setting null', function () {
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);

    // Set answer first
    $this->patchJson("/api/attempts/{$attempt->id}/answer", [
        'question_index' => 0,
        'answer' => 'A',
    ]);

    // Clear it
    $response = $this->patchJson("/api/attempts/{$attempt->id}/answer", [
        'question_index' => 0,
        'answer' => null,
    ]);

    $response->assertOk();
    expect($response->json('attempt.answers.0.answer'))->toBeNull();
    expect($response->json('attempt.answers.0.status'))->toBe('unanswered');
});

test('cannot update answer after submission', function () {
    $attempt = Attempt::factory()->finished()->create(['kangourou_session_id' => $this->session->id]);

    $response = $this->patchJson("/api/attempts/{$attempt->id}/answer", [
        'question_index' => 0,
        'answer' => 'A',
    ]);

    $response->assertForbidden();
});

test('cannot update answer for expired session outside the grace window', function () {
    $session = KangourouSession::factory()->expired()->create([
        'paper_id' => $this->paper->id,
        'expires_at' => now()->subMinutes(5),
    ]);
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $session->id]);
    $original = $attempt->answers;

    $response = $this->patchJson("/api/attempts/{$attempt->id}/answer", [
        'question_index' => 0,
        'answer' => 'A',
    ]);

    $response->assertOk();
    expect($attempt->fresh()->answers[0]['answer'])->toBe($original[0]['answer']);
});

test('can update answer for expired session inside the grace window', function () {
    $session = KangourouSession::factory()->expired()->create(['paper_id' => $this->paper->id]);
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $session->id]);

    $response = $this->patchJson("/api/attempts/{$attempt->id}/answer", [
        'question_index' => 0,
        'answer' => 'A',
    ]);

    $response->assertOk();
    expect($response->json('attempt.answers.0.answer'))->toBe('A');
});

test('can submit an attempt', function () {
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit");

    $response->assertOk();
    $response->assertJsonStructure(['message', 'score', 'attempt']);
    expect($response->json('attempt.status'))->toBe('finished');
    expect($response->json('attempt.score'))->toBeNull();
    expect($attempt->fresh()->score)->toBeNull();
});

test('cannot submit already finished attempt', function () {
    $attempt = Attempt::factory()->finished()->create(['kangourou_session_id' => $this->session->id]);

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit");

    $response->assertForbidden();
});

test('can submit an attempt when session is expired', function () {
    $session = KangourouSession::factory()->expired()->create(['paper_id' => $this->paper->id]);
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $session->id]);

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit");

    $response->assertOk();
    expect($response->json('attempt.status'))->toBe('finished');
    expect($response->json('attempt.score'))->not->toBeNull();
});

test('can recover attempt by code', function () {
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);

    $response = $this->getJson("/api/attempts/recover/{$attempt->code}");

    $response->assertOk();
    expect($response->json('attempt.id'))->toBe($attempt->id);
});

test('validates answer update with invalid question_index', function () {
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);

    $response = $this->patchJson("/api/attempts/{$attempt->id}/answer", [
        'question_index' => 30,
        'answer' => 'A',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['question_index']);
});

test('validates answer update with invalid answer letter', function () {
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);

    $response = $this->patchJson("/api/attempts/{$attempt->id}/answer", [
        'question_index' => 0,
        'answer' => 'Z',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['answer']);
});

test('authenticated user can fetch their attempts', function () {
    $user = User::factory()->create();
    $paper = Paper::factory()->withQuestions()->create();

    // Create 2 attempts in different sessions
    Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'user_id' => $user->id,
    ]);

    $session2 = KangourouSession::factory()->create(['paper_id' => $paper->id]);
    Attempt::factory()->create([
        'kangourou_session_id' => $session2->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/my/attempts');

    $response->assertOk();
    expect(count($response->json('attempts')))->toBe(2);
});

test('guest cannot fetch attempt history', function () {
    $this->getJson('/api/my/attempts')->assertUnauthorized();
});

test('submit with delayed correction does not grade while session is active', function () {
    $session = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'preferences' => ['correction' => 'delayed'],
    ]);
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $session->id]);

    $this->patchJson("/api/attempts/{$attempt->id}/answer", [
        'question_index' => 0,
        'answer' => 'A',
    ]);

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit");

    $response->assertOk();
    expect($response->json('attempt.status'))->toBe('finished')
        ->and($response->json('score'))->toBeNull()
        ->and($response->json('attempt.score'))->toBeNull();

    foreach ($response->json('attempt.answers') as $answer) {
        expect($answer['status'])->toBeIn(['answered', 'unanswered']);
    }

    $stored = $attempt->fresh();
    expect($stored->score)->toBeNull();
    foreach ($stored->answers as $answer) {
        expect($answer['status'])->toBeIn(['answered', 'unanswered']);
    }
});

test('submit with immediate correction shows answer statuses', function () {
    $session = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'preferences' => ['correction' => 'immediate'],
    ]);
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $session->id]);

    // Set some answers
    $this->patchJson("/api/attempts/{$attempt->id}/answer", [
        'question_index' => 0,
        'answer' => 'A',
    ]);

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit");

    $response->assertOk();
    expect($response->json('attempt.score'))->not->toBeNull()
        ->and($attempt->fresh()->score)->not->toBeNull();
    $answers = $response->json('attempt.answers');
    $hasCorrectOrIncorrect = collect($answers)->contains(fn ($a) => in_array($a['status'], ['correct', 'incorrect']));
    expect($hasCorrectOrIncorrect)->toBeTrue();
});

test('show attempt with delayed correction masks statuses while session active', function () {
    $session = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'preferences' => ['correction' => 'delayed'],
    ]);
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $session->id]);
    $this->postJson("/api/attempts/{$attempt->id}/submit");

    $response = $this->getJson("/api/attempts/{$attempt->id}");

    $response->assertOk();
    foreach ($response->json('attempt.answers') as $answer) {
        expect($answer['status'])->toBeIn(['answered', 'unanswered']);
    }
});

test('show attempt with delayed correction reveals statuses after session expires', function () {
    $session = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'preferences' => ['correction' => 'delayed'],
    ]);
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $session->id]);

    $this->patchJson("/api/attempts/{$attempt->id}/answer", [
        'question_index' => 0,
        'answer' => 'A',
    ]);
    $this->postJson("/api/attempts/{$attempt->id}/submit");

    expect($attempt->fresh()->score)->toBeNull();

    $session->update(['expires_at' => now()->subMinute()]);
    (new ExpireKangourouSessions)->handle(app(GradingService::class));

    $response = $this->getJson("/api/attempts/{$attempt->id}");

    $response->assertOk();
    expect($attempt->fresh()->score)->not->toBeNull();
    $answers = $response->json('attempt.answers');
    $hasCorrectOrIncorrect = collect($answers)->contains(fn ($a) => in_array($a['status'], ['correct', 'incorrect']));
    expect($hasCorrectOrIncorrect)->toBeTrue();
});

test('delayed submit grades immediately when the session has already expired', function () {
    $session = KangourouSession::factory()->expired()->create([
        'paper_id' => $this->paper->id,
        'preferences' => ['correction' => 'delayed'],
    ]);
    $answers = Attempt::defaultAnswers();
    $answers[0] = ['answer' => 'A', 'status' => 'answered'];
    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $session->id,
        'answers' => $answers,
    ]);

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit");

    $response->assertOk();
    expect($response->json('attempt.score'))->not->toBeNull()
        ->and($attempt->fresh()->score)->not->toBeNull();
    $hasCorrectOrIncorrect = collect($attempt->fresh()->answers)->contains(
        fn ($a) => in_array($a['status'], ['correct', 'incorrect'])
    );
    expect($hasCorrectOrIncorrect)->toBeTrue();
});

test('delayed submit grades immediately when expires_at has passed but status is still active', function () {
    $session = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'status' => 'active',
        'expires_at' => now()->subMinute(),
        'preferences' => ['correction' => 'delayed'],
    ]);
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $session->id]);

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit");

    $response->assertOk();
    expect($response->json('attempt.status'))->toBe('finished')
        ->and($attempt->fresh()->score)->not->toBeNull();
});

test('cannot update answers after a delayed submit', function () {
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);

    $this->postJson("/api/attempts/{$attempt->id}/submit")->assertOk();

    $response = $this->patchJson("/api/attempts/{$attempt->id}/answer", [
        'question_index' => 0,
        'answer' => 'A',
    ]);

    $response->assertForbidden();
});

test('guest can create attempt with a name', function () {
    $response = $this->postJson("/api/kangourou-sessions/{$this->session->code}/attempts", [
        'name' => 'Jean Dupont',
    ]);

    $response->assertCreated();
    expect($response->json('attempt.name'))->toBe('Jean Dupont');
});

test('authenticated user attempt uses account name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson("/api/kangourou-sessions/{$this->session->code}/attempts", [
        'name' => 'Should be ignored',
    ]);

    $response->assertCreated();
    expect($response->json('attempt.name'))->toBe($user->name);
});

test('timer is saved when updating an answer', function () {
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);

    $response = $this->patchJson("/api/attempts/{$attempt->id}/answer", [
        'question_index' => 0,
        'answer' => 'A',
        'timer' => 1500,
    ]);

    $response->assertOk();
    expect($attempt->fresh()->timer)->toBe(1500);
});

test('timer and termination are saved on submit', function () {
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit", [
        'timer' => 900,
        'termination' => 'submitted',
    ]);

    $response->assertOk();
    $fresh = $attempt->fresh();
    expect($fresh->timer)->toBe(900);
    expect($fresh->termination)->toBe('submitted');
});

test('termination defaults to submitted on submit', function () {
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit");

    $response->assertOk();
    expect($attempt->fresh()->termination)->toBe('submitted');
});

test('termination can be blurred or timeout', function () {
    $attempt1 = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);
    $attempt2 = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);

    $this->postJson("/api/attempts/{$attempt1->id}/submit", ['termination' => 'blurred']);
    $this->postJson("/api/attempts/{$attempt2->id}/submit", ['termination' => 'timeout']);

    expect($attempt1->fresh()->termination)->toBe('blurred');
    expect($attempt2->fresh()->termination)->toBe('timeout');
});

test('invalid termination value is rejected', function () {
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit", [
        'termination' => 'invalid',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['termination']);
});

test('new attempt has default termination of none', function () {
    $response = $this->postJson("/api/kangourou-sessions/{$this->session->code}/attempts");

    $response->assertCreated();
    expect($response->json('attempt.termination'))->toBe('none');
});

test('authenticated user cannot create multiple attempts for the same session', function () {
    $user = User::factory()->create();

    // Create first attempt
    $response1 = $this->actingAs($user)
        ->postJson("/api/kangourou-sessions/{$this->session->code}/attempts");

    $response1->assertCreated();
    $firstAttemptId = $response1->json('attempt.id');

    // Attempt to create second attempt
    $response2 = $this->actingAs($user)
        ->postJson("/api/kangourou-sessions/{$this->session->code}/attempts");

    $response2->assertStatus(409);
    $response2->assertJson([
        'message' => 'You already have an attempt for this session.',
        'attempt' => ['id' => $firstAttemptId],
    ]);
});

test('guest cannot create multiple attempts for the same session with the same name', function () {
    $response1 = $this->postJson("/api/kangourou-sessions/{$this->session->code}/attempts", [
        'name' => 'Alice',
    ]);

    $response1->assertCreated();
    $firstAttemptId = $response1->json('attempt.id');

    $response2 = $this->postJson("/api/kangourou-sessions/{$this->session->code}/attempts", [
        'name' => 'Alice',
    ]);

    $response2->assertStatus(409);
    $response2->assertJson([
        'requires_rejoin' => true,
        'attempt' => ['id' => $firstAttemptId],
    ]);
});

test('guest cannot join a private session', function () {
    $session = KangourouSession::factory()->private()->create(['paper_id' => $this->paper->id]);

    $response = $this->postJson("/api/kangourou-sessions/{$session->code}/attempts");

    $response->assertUnauthorized();
});

test('authenticated user not in any division cannot join a private session', function () {
    $user = User::factory()->create();
    $session = KangourouSession::factory()->private()->create(['paper_id' => $this->paper->id]);

    $response = $this->actingAs($user)->postJson("/api/kangourou-sessions/{$session->code}/attempts");

    $response->assertForbidden();
});

test('student in the session division can join a private session', function () {
    $user = User::factory()->create();
    $session = KangourouSession::factory()->private()->create(['paper_id' => $this->paper->id]);
    $division = Division::factory()->create();
    $division->students()->attach($user->id);
    $session->divisions()->attach($division->id);

    $response = $this->actingAs($user)->postJson("/api/kangourou-sessions/{$session->code}/attempts");

    $response->assertCreated();
});

test('student in a different division cannot join a private session', function () {
    $user = User::factory()->create();
    $session = KangourouSession::factory()->private()->create(['paper_id' => $this->paper->id]);

    // User belongs to a division, but it's not linked to the session
    $otherDivision = Division::factory()->create();
    $otherDivision->students()->attach($user->id);

    $response = $this->actingAs($user)->postJson("/api/kangourou-sessions/{$session->code}/attempts");

    $response->assertForbidden();
});

// --- Ownership checks (C1 & C2) ---

test('authenticated user cannot update another users attempt answer', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'user_id' => $owner->id,
    ]);

    $response = $this->actingAs($intruder)->patchJson("/api/attempts/{$attempt->id}/answer", [
        'question_index' => 0,
        'answer' => 'B',
    ]);

    $response->assertForbidden();
});

test('authenticated user cannot submit another users attempt', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'user_id' => $owner->id,
    ]);

    $response = $this->actingAs($intruder)->postJson("/api/attempts/{$attempt->id}/submit");

    $response->assertForbidden();
});

test('authenticated user can update their own attempt answer', function () {
    $user = User::factory()->create();

    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->patchJson("/api/attempts/{$attempt->id}/answer", [
        'question_index' => 0,
        'answer' => 'C',
    ]);

    $response->assertOk();
    expect($response->json('attempt.answers.0.answer'))->toBe('C');
});

test('authenticated user can submit their own attempt', function () {
    $user = User::factory()->create();

    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->postJson("/api/attempts/{$attempt->id}/submit");

    $response->assertOk();
    expect($response->json('attempt.status'))->toBe('finished');
});

test('authenticated user cannot view another users attempt', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'user_id' => $owner->id,
    ]);

    $response = $this->actingAs($intruder)->getJson("/api/attempts/{$attempt->id}");

    $response->assertForbidden();
});

test('session author can view any attempt in their session', function () {
    $author = User::factory()->create();
    $student = User::factory()->create();

    $session = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'author_id' => $author->id,
    ]);

    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $session->id,
        'user_id' => $student->id,
    ]);

    $response = $this->actingAs($author)->getJson("/api/attempts/{$attempt->id}");

    $response->assertOk();
    expect($response->json('attempt.id'))->toBe($attempt->id);
});

test('guest can still update and submit their own attempt', function () {
    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'user_id' => null,
    ]);

    $this->patchJson("/api/attempts/{$attempt->id}/answer", [
        'question_index' => 0,
        'answer' => 'A',
    ])->assertOk();

    $this->postJson("/api/attempts/{$attempt->id}/submit")->assertOk();
});

test('creating an attempt broadcasts a summary event without answers', function () {
    Event::fake();

    $response = $this->postJson("/api/kangourou-sessions/{$this->session->code}/attempts", [
        'name' => 'Jean DUPONT',
    ]);

    $response->assertCreated();

    Event::assertDispatched(AttemptUpdated::class, function (AttemptUpdated $event) {
        $payload = $event->broadcastWith()['attempt'];

        return $payload['name'] === 'Jean DUPONT'
            && $payload['status'] === 'inProgress'
            && $payload['answered_count'] === 0
            && $payload['total_questions'] === 26
            && $payload['extra_time'] === 0
            && ! array_key_exists('answers', $payload)
            && ! array_key_exists('user', $payload);
    });
});

test('can sync multiple answers in one request', function () {
    Event::fake();
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);

    $response = $this->patchJson("/api/attempts/{$attempt->id}/sync", [
        'timer' => 421,
        'changes' => [
            ['question_index' => 0, 'answer' => 'A'],
            ['question_index' => 7, 'answer' => 'C'],
        ],
    ]);

    $response->assertNoContent();

    $fresh = $attempt->fresh();

    expect($fresh->timer)->toBe(421)
        ->and($fresh->answers[0]['answer'])->toBe('A')
        ->and($fresh->answers[0]['status'])->toBe('answered')
        ->and($fresh->answers[7]['answer'])->toBe('C')
        ->and($fresh->answers[7]['status'])->toBe('answered');

    Event::assertDispatched(AttemptUpdated::class, function (AttemptUpdated $event) use ($attempt) {
        $payload = $event->broadcastWith()['attempt'];

        return $event->attempt->is($attempt->fresh())
            && $payload['timer'] === 421
            && $payload['answered_count'] === 2
            && $payload['total_questions'] === 26
            && $payload['score'] === null
            && is_string($payload['updated_at'] ?? null)
            && ! array_key_exists('answers', $payload);
    });
});

test('sync can clear an answer', function () {
    Event::fake();
    $answers = Attempt::defaultAnswers();
    $answers[0] = ['answer' => 'A', 'status' => 'answered'];
    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'answers' => $answers,
    ]);

    $this->patchJson("/api/attempts/{$attempt->id}/sync", [
        'changes' => [
            ['question_index' => 0, 'answer' => null],
        ],
    ])->assertNoContent();

    expect($attempt->fresh()->answers[0]['answer'])->toBeNull()
        ->and($attempt->fresh()->answers[0]['status'])->toBe('unanswered');
});

test('sync accepts a numeric answer for question 25', function () {
    Event::fake();
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);

    $this->patchJson("/api/attempts/{$attempt->id}/sync", [
        'timer' => 100,
        'changes' => [
            ['question_index' => 24, 'answer' => 17],
        ],
    ])->assertNoContent();

    expect($attempt->fresh()->answers[24]['answer'])->toBe(17)
        ->and($attempt->fresh()->answers[24]['status'])->toBe('answered');
});

test('sync rejects invalid question indices', function () {
    Event::fake();
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);

    $this->patchJson("/api/attempts/{$attempt->id}/sync", [
        'changes' => [
            ['question_index' => 30, 'answer' => 'A'],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors(['changes.0.question_index']);

    Event::assertNotDispatched(AttemptUpdated::class);
});

test('sync rejects invalid letters and invalid numeric values', function () {
    Event::fake();
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);

    $this->patchJson("/api/attempts/{$attempt->id}/sync", [
        'changes' => [
            ['question_index' => 0, 'answer' => 'Z'],
        ],
    ])->assertUnprocessable();

    $this->patchJson("/api/attempts/{$attempt->id}/sync", [
        'changes' => [
            ['question_index' => 24, 'answer' => 101],
        ],
    ])->assertUnprocessable();

    Event::assertNotDispatched(AttemptUpdated::class);
});

test('sync rejects unauthorized authenticated users', function () {
    Event::fake();
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'user_id' => $owner->id,
    ]);

    $this->actingAs($intruder)->patchJson("/api/attempts/{$attempt->id}/sync", [
        'changes' => [
            ['question_index' => 0, 'answer' => 'A'],
        ],
    ])->assertForbidden();

    Event::assertNotDispatched(AttemptUpdated::class);
});

test('guest can sync a guest attempt', function () {
    Event::fake();
    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'user_id' => null,
    ]);

    $this->patchJson("/api/attempts/{$attempt->id}/sync", [
        'timer' => 50,
        'changes' => [
            ['question_index' => 1, 'answer' => 'B'],
        ],
    ])->assertNoContent();

    expect($attempt->fresh()->answers[1]['answer'])->toBe('B');
    Event::assertDispatched(AttemptUpdated::class);
});

test('sync rejects a finished attempt', function () {
    Event::fake();
    $attempt = Attempt::factory()->finished()->create(['kangourou_session_id' => $this->session->id]);

    $this->patchJson("/api/attempts/{$attempt->id}/sync", [
        'changes' => [
            ['question_index' => 0, 'answer' => 'A'],
        ],
    ])->assertForbidden();

    Event::assertNotDispatched(AttemptUpdated::class);
});

test('sync persists answers inside the post-expiry grace window', function () {
    Event::fake();
    $session = KangourouSession::factory()->expired()->create(['paper_id' => $this->paper->id]);
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $session->id]);

    $this->patchJson("/api/attempts/{$attempt->id}/sync", [
        'changes' => [
            ['question_index' => 0, 'answer' => 'A'],
        ],
    ])->assertOk()
        ->assertJson([
            'session_expired' => true,
            'saved' => true,
        ]);

    expect($attempt->fresh()->answers[0]['answer'])->toBe('A');
    Event::assertDispatched(AttemptUpdated::class);
});

test('sync does not persist answers outside the post-expiry grace window', function () {
    Event::fake();
    $session = KangourouSession::factory()->expired()->create([
        'paper_id' => $this->paper->id,
        'expires_at' => now()->subMinutes(5),
    ]);
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $session->id]);

    $this->patchJson("/api/attempts/{$attempt->id}/sync", [
        'changes' => [
            ['question_index' => 0, 'answer' => 'A'],
        ],
    ])->assertOk()
        ->assertJson([
            'session_expired' => true,
            'saved' => false,
        ]);

    expect($attempt->fresh()->answers[0]['answer'])->toBeNull();
    Event::assertNotDispatched(AttemptUpdated::class);
});

test('sync does not broadcast when nothing changed', function () {
    Event::fake();
    $answers = Attempt::defaultAnswers();
    $answers[0] = ['answer' => 'A', 'status' => 'answered'];
    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'answers' => $answers,
        'timer' => 100,
    ]);

    $this->patchJson("/api/attempts/{$attempt->id}/sync", [
        'timer' => 100,
        'changes' => [
            ['question_index' => 0, 'answer' => 'A'],
        ],
    ])->assertNoContent();

    Event::assertNotDispatched(AttemptUpdated::class);
});

test('submit persists unsynced answers from the request body', function () {
    Event::fake();
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);
    $answers = Attempt::defaultAnswers();
    $answers[0] = ['answer' => 'A', 'status' => 'answered'];
    $answers[3] = ['answer' => 'D', 'status' => 'answered'];

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit", [
        'timer' => 12,
        'termination' => 'submitted',
        'answers' => $answers,
    ]);

    $response->assertOk();
    $fresh = $attempt->fresh();
    expect($fresh->answers[0]['answer'])->toBe('A')
        ->and($fresh->answers[3]['answer'])->toBe('D')
        ->and($fresh->status)->toBe('finished');

    Event::assertDispatched(AttemptUpdated::class, function (AttemptUpdated $event) {
        $payload = $event->broadcastWith()['attempt'];

        return $payload['status'] === 'finished'
            && $payload['termination'] === 'submitted'
            && $payload['answered_count'] === 2
            && ! array_key_exists('answers', $payload);
    });
});

test('submit with delayed correction still does not grade when answers are included', function () {
    $session = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'preferences' => ['correction' => 'delayed'],
    ]);
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $session->id]);
    $answers = Attempt::defaultAnswers();
    $answers[0] = ['answer' => 'A', 'status' => 'answered'];

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit", [
        'answers' => $answers,
    ]);

    $response->assertOk();
    expect($response->json('score'))->toBeNull()
        ->and($attempt->fresh()->score)->toBeNull()
        ->and($attempt->fresh()->answers[0]['answer'])->toBe('A');
});

test('expired in-progress submit with answers inside grace window persists and grades', function () {
    Queue::fake();
    $session = KangourouSession::factory()->expired()->create(['paper_id' => $this->paper->id]);
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $session->id]);
    $answers = Attempt::defaultAnswers();
    $answers[0] = ['answer' => 'A', 'status' => 'answered'];

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit", [
        'timer' => 12,
        'termination' => 'submitted',
        'answers' => $answers,
    ]);

    $response->assertOk();
    $fresh = $attempt->fresh();
    expect($fresh->answers[0]['answer'])->toBe('A')
        ->and($fresh->status)->toBe('finished')
        ->and($fresh->score)->not->toBeNull();
    Queue::assertPushed(UpdateMasteryAndDifficulty::class, fn ($job) => $job->attempt->id === $attempt->id);
});

test('expired timeout-finished submit overwrites answers inside the grace window without a second mastery job', function () {
    Queue::fake();
    $session = KangourouSession::factory()->expired()->create(['paper_id' => $this->paper->id]);
    $originalAnswers = Attempt::defaultAnswers();
    $originalAnswers[0] = ['answer' => 'A', 'status' => 'incorrect'];
    $attempt = Attempt::factory()->finished()->create([
        'kangourou_session_id' => $session->id,
        'termination' => 'timeout',
        'answers' => $originalAnswers,
        'score' => 10.0,
    ]);
    $answers = Attempt::defaultAnswers();
    $questions = $this->paper->questions()->orderByPivot('order')->get();
    $answers[0] = ['answer' => $questions[0]->correct_answer, 'status' => 'answered'];

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit", [
        'termination' => 'timeout',
        'answers' => $answers,
    ]);

    $response->assertOk();
    $fresh = $attempt->fresh();
    expect($fresh->answers[0]['answer'])->toBe($questions[0]->correct_answer)
        ->and($fresh->status)->toBe('finished')
        ->and((float) $fresh->score)->not->toEqual(10.0);
    Queue::assertNotPushed(UpdateMasteryAndDifficulty::class);
});

test('expired already-finished submit ignores answers outside the grace window', function () {
    $session = KangourouSession::factory()->expired()->create([
        'paper_id' => $this->paper->id,
        'expires_at' => now()->subMinutes(5),
    ]);
    $originalAnswers = Attempt::defaultAnswers();
    $originalAnswers[0] = ['answer' => 'A', 'status' => 'answered'];
    $attempt = Attempt::factory()->finished()->create([
        'kangourou_session_id' => $session->id,
        'termination' => 'timeout',
        'answers' => $originalAnswers,
        'score' => 42.0,
    ]);
    $answers = Attempt::defaultAnswers();
    $answers[0] = ['answer' => 'B', 'status' => 'answered'];

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit", [
        'answers' => $answers,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message', 'score', 'attempt']);
    expect($attempt->fresh()->answers[0]['answer'])->toBe('A')
        ->and((float) $attempt->fresh()->score)->toEqual(42.0);
});

test('expired already-finished submit without answers returns success unchanged', function () {
    $session = KangourouSession::factory()->expired()->create(['paper_id' => $this->paper->id]);
    $attempt = Attempt::factory()->finished()->create([
        'kangourou_session_id' => $session->id,
        'termination' => 'timeout',
        'score' => 33.0,
    ]);

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit");

    $response->assertOk()
        ->assertJsonStructure(['message', 'score', 'attempt']);
    expect($attempt->fresh()->score)->toEqual(33.0)
        ->and($attempt->fresh()->status)->toBe('finished');
});

test('guest can submit after expiry inside the grace window', function () {
    $session = KangourouSession::factory()->expired()->create(['paper_id' => $this->paper->id]);
    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $session->id,
        'user_id' => null,
        'name' => 'Guest',
    ]);
    $answers = Attempt::defaultAnswers();
    $answers[1] = ['answer' => 'C', 'status' => 'answered'];

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit", [
        'answers' => $answers,
    ]);

    $response->assertOk();
    expect($attempt->fresh()->answers[1]['answer'])->toBe('C')
        ->and($attempt->fresh()->status)->toBe('finished');
});

test('authenticated user cannot submit another users attempt after expiry', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $session = KangourouSession::factory()->expired()->create(['paper_id' => $this->paper->id]);
    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $session->id,
        'user_id' => $owner->id,
    ]);

    $response = $this->actingAs($intruder)->postJson("/api/attempts/{$attempt->id}/submit");

    $response->assertForbidden();
});

test('late submit inside the grace window recomputes session analysis', function () {
    $division = Division::factory()->create();
    $student = User::factory()->create();
    $division->students()->attach($student->id);

    $session = KangourouSession::factory()->expired()->create(['paper_id' => $this->paper->id]);
    $division->kangourouSessions()->attach($session->id);

    $questions = $this->paper->questions()->orderByPivot('order')->get();
    $originalAnswers = Attempt::defaultAnswers();
    $attempt = Attempt::factory()->finished()->create([
        'kangourou_session_id' => $session->id,
        'user_id' => $student->id,
        'termination' => 'timeout',
        'answers' => $originalAnswers,
        'score' => 24.0,
    ]);

    $answers = Attempt::defaultAnswers();
    $answers[0] = ['answer' => $questions[0]->correct_answer, 'status' => 'answered'];

    $this->postJson("/api/attempts/{$attempt->id}/submit", [
        'answers' => $answers,
    ])->assertOk();

    $pivot = $division->kangourouSessions()->where('kangourou_session_id', $session->id)->first()->pivot;

    expect($pivot->analysis)->toBeArray()
        ->and($pivot->analysis[0]['success_ratio'])->toEqual(1.0);
});
