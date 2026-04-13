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
    Attempt::factory()->count(2)->create([
        'kangourou_session_id' => $this->session->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/my/attempts');

    $response->assertOk();
    expect(count($response->json('attempts')))->toBe(2);
});

test('guest cannot fetch attempt history', function () {
    $this->getJson('/api/my/attempts')->assertUnauthorized();
});
