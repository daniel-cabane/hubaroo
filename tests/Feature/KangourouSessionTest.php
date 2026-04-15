<?php

use App\Models\Attempt;
use App\Models\KangourouSession;
use App\Models\Paper;
use App\Models\User;

beforeEach(function () {
    $this->paper = Paper::factory()->withQuestions()->create();
});

test('guest cannot create a kangourou session', function () {
    $response = $this->postJson('/api/kangourou-sessions', [
        'paper_id' => $this->paper->id,
    ]);

    $response->assertUnauthorized();
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

test('immediate correction shows answers when attempt is finished', function () {
    $session = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'preferences' => ['correction' => 'immediate'],
    ]);

    $attempt = Attempt::factory()->finished()->create([
        'kangourou_session_id' => $session->id,
    ]);

    $response = $this->getJson("/api/kangourou-sessions/{$session->code}?attempt_id={$attempt->id}");

    $response->assertOk();
    $questions = $response->json('session.paper.questions');
    foreach ($questions as $question) {
        expect($question)->toHaveKey('correct_answer');
    }
});

test('immediate correction hides answers when attempt is in progress', function () {
    $session = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'preferences' => ['correction' => 'immediate'],
    ]);

    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $session->id,
    ]);

    $response = $this->getJson("/api/kangourou-sessions/{$session->code}?attempt_id={$attempt->id}");

    $response->assertOk();
    $questions = $response->json('session.paper.questions');
    foreach ($questions as $question) {
        expect($question)->not->toHaveKey('correct_answer');
    }
});

test('delayed correction hides answers even when attempt is finished and session active', function () {
    $session = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'preferences' => ['correction' => 'delayed'],
    ]);

    $attempt = Attempt::factory()->finished()->create([
        'kangourou_session_id' => $session->id,
    ]);

    $response = $this->getJson("/api/kangourou-sessions/{$session->code}?attempt_id={$attempt->id}");

    $response->assertOk();
    $questions = $response->json('session.paper.questions');
    foreach ($questions as $question) {
        expect($question)->not->toHaveKey('correct_answer');
    }
});

test('delayed correction shows answers when session is expired', function () {
    $session = KangourouSession::factory()->expired()->create([
        'paper_id' => $this->paper->id,
        'preferences' => ['correction' => 'delayed'],
    ]);

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
    $user = User::factory()->create();
    $response = $this->actingAs($user)->postJson('/api/kangourou-sessions', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['paper_id']);
});

test('create session validates paper exists', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->postJson('/api/kangourou-sessions', ['paper_id' => 9999]);

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

test('author can update session privacy', function () {
    $user = User::factory()->create();
    $session = KangourouSession::factory()->withAuthor($user)->create([
        'paper_id' => $this->paper->id,
        'privacy' => 'public',
    ]);

    $response = $this->actingAs($user)->patchJson("/api/kangourou-sessions/{$session->id}", [
        'privacy' => 'private',
    ]);

    $response->assertOk();
    expect($response->json('session.privacy'))->toBe('private');
});

test('author can update session correction mode and grading', function () {
    $user = User::factory()->create();
    $session = KangourouSession::factory()->withAuthor($user)->create([
        'paper_id' => $this->paper->id,
    ]);

    $response = $this->actingAs($user)->patchJson("/api/kangourou-sessions/{$session->id}", [
        'preferences' => [
            'correction' => 'immediate',
            'grading' => ['tier1' => 5, 'tier2' => 6],
        ],
    ]);

    $response->assertOk();
    $prefs = $response->json('session.preferences');
    expect($prefs['correction'])->toBe('immediate');
    expect($prefs['grading']['tier1'])->toBe(5);
    expect($prefs['grading']['tier2'])->toBe(6);
});

test('update session preserves time_limit even if sent', function () {
    $user = User::factory()->create();
    $session = KangourouSession::factory()->withAuthor($user)->create([
        'paper_id' => $this->paper->id,
        'preferences' => ['time_limit' => 50, 'correction' => 'delayed'],
    ]);

    $response = $this->actingAs($user)->patchJson("/api/kangourou-sessions/{$session->id}", [
        'preferences' => ['time_limit' => 999, 'correction' => 'immediate'],
    ]);

    $response->assertOk();
    expect($response->json('session.preferences.time_limit'))->toBe(50);
    expect($response->json('session.preferences.correction'))->toBe('immediate');
});

test('non-author cannot update session', function () {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $session = KangourouSession::factory()->withAuthor($author)->create([
        'paper_id' => $this->paper->id,
    ]);

    $response = $this->actingAs($other)->patchJson("/api/kangourou-sessions/{$session->id}", [
        'privacy' => 'private',
    ]);

    $response->assertForbidden();
});

test('guest cannot update session', function () {
    $user = User::factory()->create();
    $session = KangourouSession::factory()->withAuthor($user)->create([
        'paper_id' => $this->paper->id,
    ]);

    $response = $this->patchJson("/api/kangourou-sessions/{$session->id}", [
        'privacy' => 'private',
    ]);

    $response->assertUnauthorized();
});

test('author can fetch session details with attempts', function () {
    $user = User::factory()->create();
    $session = KangourouSession::factory()->withAuthor($user)->create([
        'paper_id' => $this->paper->id,
    ]);

    // Create some attempts
    Attempt::factory()->count(2)->create(['kangourou_session_id' => $session->id]);
    Attempt::factory()->finished()->create(['kangourou_session_id' => $session->id]);

    $response = $this->actingAs($user)->getJson("/api/kangourou-sessions/{$session->id}/details");

    $response->assertOk();
    $response->assertJsonStructure(['session' => ['id', 'code', 'paper', 'attempts']]);
    expect(count($response->json('session.attempts')))->toBe(3);
});

test('non-author cannot fetch session details', function () {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $session = KangourouSession::factory()->withAuthor($author)->create([
        'paper_id' => $this->paper->id,
    ]);

    $response = $this->actingAs($other)->getJson("/api/kangourou-sessions/{$session->id}/details");

    $response->assertForbidden();
});

test('guest cannot fetch session details', function () {
    $user = User::factory()->create();
    $session = KangourouSession::factory()->withAuthor($user)->create([
        'paper_id' => $this->paper->id,
    ]);

    $response = $this->getJson("/api/kangourou-sessions/{$session->id}/details");

    $response->assertUnauthorized();
});

test('author can change session code', function () {
    $user = User::factory()->create();
    $session = KangourouSession::factory()->withAuthor($user)->create([
        'paper_id' => $this->paper->id,
    ]);
    $oldCode = $session->code;

    $response = $this->actingAs($user)->patchJson("/api/kangourou-sessions/{$session->id}/change-code");

    $response->assertOk();
    $newCode = $response->json('session.code');
    expect($newCode)->not->toBe($oldCode);
    expect(strlen($newCode))->toBe(6);
    expect($session->fresh()->code)->toBe($newCode);
});

test('non-author cannot change session code', function () {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $session = KangourouSession::factory()->withAuthor($author)->create([
        'paper_id' => $this->paper->id,
    ]);

    $response = $this->actingAs($other)->patchJson("/api/kangourou-sessions/{$session->id}/change-code");

    $response->assertForbidden();
});
