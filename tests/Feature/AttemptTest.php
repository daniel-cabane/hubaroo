<?php

use App\Models\Attempt;
use App\Models\KangourouSession;
use App\Models\Paper;
use App\Models\User;

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

test('cannot update answer for expired session', function () {
    $session = KangourouSession::factory()->expired()->create(['paper_id' => $this->paper->id]);
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $session->id]);

    $response = $this->patchJson("/api/attempts/{$attempt->id}/answer", [
        'question_index' => 0,
        'answer' => 'A',
    ]);

    $response->assertForbidden();
});

test('can submit an attempt', function () {
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit");

    $response->assertOk();
    $response->assertJsonStructure(['message', 'score', 'attempt']);
    expect($response->json('attempt.status'))->toBe('finished');
    expect($response->json('attempt.score'))->not->toBeNull();
});

test('cannot submit already finished attempt', function () {
    $attempt = Attempt::factory()->finished()->create(['kangourou_session_id' => $this->session->id]);

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit");

    $response->assertForbidden();
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

test('submit with delayed correction masks answer statuses while session active', function () {
    $session = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'preferences' => ['correction' => 'delayed'],
    ]);
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $session->id]);

    $response = $this->postJson("/api/attempts/{$attempt->id}/submit");

    $response->assertOk();
    expect($response->json('attempt.status'))->toBe('finished');
    foreach ($response->json('attempt.answers') as $answer) {
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

    // Expire the session
    $session->update(['status' => 'expired', 'expires_at' => now()->subMinute()]);

    $response = $this->getJson("/api/attempts/{$attempt->id}");

    $response->assertOk();
    $answers = $response->json('attempt.answers');
    $hasCorrectOrIncorrect = collect($answers)->contains(fn ($a) => in_array($a['status'], ['correct', 'incorrect']));
    expect($hasCorrectOrIncorrect)->toBeTrue();
});

test('guest can create attempt with a name', function () {
    $response = $this->postJson("/api/kangourou-sessions/{$this->session->code}/attempts", [
        'name' => 'Jean Dupont',
    ]);

    $response->assertCreated();
    expect($response->json('attempt.name'))->toBe('Jean Dupont');
});

test('authenticated user attempt has null name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson("/api/kangourou-sessions/{$this->session->code}/attempts", [
        'name' => 'Should be ignored',
    ]);

    $response->assertCreated();
    expect($response->json('attempt.name'))->toBeNull();
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

test('guest users can create multiple attempts for the same session', function () {
    // Create first guest attempt
    $response1 = $this->postJson("/api/kangourou-sessions/{$this->session->code}/attempts", [
        'name' => 'Guest 1',
    ]);

    $response1->assertCreated();

    // Create second guest attempt
    $response2 = $this->postJson("/api/kangourou-sessions/{$this->session->code}/attempts", [
        'name' => 'Guest 2',
    ]);

    $response2->assertCreated();
    expect($response2->json('attempt.name'))->toBe('Guest 2');
});
