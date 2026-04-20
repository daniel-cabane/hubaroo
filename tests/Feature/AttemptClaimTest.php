<?php

use App\Models\Attempt;
use App\Models\KangourouSession;
use App\Models\Paper;
use App\Models\User;

beforeEach(function () {
    $this->paper = Paper::factory()->withQuestions()->create();
    $this->session = KangourouSession::factory()->create(['paper_id' => $this->paper->id]);
});

test('guest cannot claim attempts', function () {
    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'user_id' => null,
    ]);

    $response = $this->postJson('/api/attempts/claim', [
        'attempt_ids' => [$attempt->id],
    ]);

    $response->assertUnauthorized();
});

test('authenticated user can claim guest attempts', function () {
    $user = User::factory()->create();
    $session2 = KangourouSession::factory()->create(['paper_id' => $this->paper->id]);

    $attempt1 = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'user_id' => null,
        'name' => 'Guest Player',
    ]);
    $attempt2 = Attempt::factory()->create([
        'kangourou_session_id' => $session2->id,
        'user_id' => null,
        'name' => 'Another Guest',
    ]);

    $response = $this->actingAs($user)->postJson('/api/attempts/claim', [
        'attempt_ids' => [$attempt1->id, $attempt2->id],
    ]);

    $response->assertOk();
    expect($response->json('claimed'))->toBe(2);

    $attempt1->refresh();
    $attempt2->refresh();
    expect($attempt1->user_id)->toBe($user->id);
    expect($attempt1->name)->toBe($user->name);
    expect($attempt2->user_id)->toBe($user->id);
});

test('cannot claim attempts that already belong to another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'user_id' => $otherUser->id,
    ]);

    $response = $this->actingAs($user)->postJson('/api/attempts/claim', [
        'attempt_ids' => [$attempt->id],
    ]);

    $response->assertOk();
    expect($response->json('claimed'))->toBe(0);

    $attempt->refresh();
    expect($attempt->user_id)->toBe($otherUser->id);
});

test('claim validates attempt_ids is required', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/attempts/claim', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['attempt_ids']);
});

test('skips guest attempt if user already has attempt for that session', function () {
    $user = User::factory()->create();

    // User already has an attempt for this session
    Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'user_id' => $user->id,
    ]);

    $guestAttempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'user_id' => null,
    ]);

    $response = $this->actingAs($user)->postJson('/api/attempts/claim', [
        'attempt_ids' => [$guestAttempt->id],
    ]);

    $response->assertOk();
    expect($response->json('claimed'))->toBe(0);

    $guestAttempt->refresh();
    expect($guestAttempt->user_id)->toBeNull();
});
