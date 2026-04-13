<?php

use App\Models\KangourouSession;
use App\Models\Paper;
use App\Models\User;

beforeEach(function () {
    $this->paper = Paper::factory()->withQuestions()->create();
});

test('guest can create a kangourou session', function () {
    $response = $this->postJson('/api/kangourou-sessions', [
        'paper_id' => $this->paper->id,
    ]);

    $response->assertCreated();
    $response->assertJsonStructure(['message', 'session' => ['id', 'code', 'status']]);
    expect($response->json('session.author_id'))->toBeNull();
    expect($response->json('session.status'))->toBe('active');
    expect(strlen($response->json('session.code')))->toBe(6);
});

test('authenticated user can create a session with author_id', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/kangourou-sessions', [
        'paper_id' => $this->paper->id,
    ]);

    $response->assertCreated();
    expect($response->json('session.author_id'))->toBe($user->id);
});

test('session code is unique 6-char alphanumeric', function () {
    $code = KangourouSession::generateCode();

    expect(strlen($code))->toBe(6);
    expect($code)->toMatch('/^[A-Z0-9]+$/');
});

test('can fetch session by code with questions', function () {
    $session = KangourouSession::factory()->create(['paper_id' => $this->paper->id]);

    $response = $this->getJson("/api/kangourou-sessions/{$session->code}");

    $response->assertOk();
    $response->assertJsonStructure(['session' => ['id', 'code', 'paper' => ['questions']]]);
    expect(count($response->json('session.paper.questions')))->toBe(26);
});

test('correct answers are hidden while session is active', function () {
    $session = KangourouSession::factory()->create(['paper_id' => $this->paper->id]);

    $response = $this->getJson("/api/kangourou-sessions/{$session->code}");

    $response->assertOk();
    $questions = $response->json('session.paper.questions');
    foreach ($questions as $question) {
        expect($question)->not->toHaveKey('correct_answer');
    }
});

test('correct answers are visible when session is expired', function () {
    $session = KangourouSession::factory()->expired()->create(['paper_id' => $this->paper->id]);

    $response = $this->getJson("/api/kangourou-sessions/{$session->code}");

    $response->assertOk();
    $questions = $response->json('session.paper.questions');
    foreach ($questions as $question) {
        expect($question)->toHaveKey('correct_answer');
    }
});

test('can activate a session', function () {
    $session = KangourouSession::factory()->draft()->create(['paper_id' => $this->paper->id]);

    $response = $this->patchJson("/api/kangourou-sessions/{$session->id}/activate");

    $response->assertOk();
    expect($response->json('session.status'))->toBe('active');
});

test('create session validates paper_id required', function () {
    $response = $this->postJson('/api/kangourou-sessions', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['paper_id']);
});

test('create session validates paper exists', function () {
    $response = $this->postJson('/api/kangourou-sessions', ['paper_id' => 9999]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['paper_id']);
});

test('authenticated user can fetch their sessions', function () {
    $user = User::factory()->create();
    KangourouSession::factory()->count(3)->create([
        'paper_id' => $this->paper->id,
        'author_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/my/kangourou-sessions');

    $response->assertOk();
    expect(count($response->json('sessions')))->toBe(3);
});

test('guest cannot fetch session history', function () {
    $this->getJson('/api/my/kangourou-sessions')->assertUnauthorized();
});
