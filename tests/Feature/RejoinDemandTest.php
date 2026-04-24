<?php

use App\Events\RejoinDemandCreated;
use App\Events\RejoinDemandResolved;
use App\Models\Attempt;
use App\Models\KangourouSession;
use App\Models\Paper;
use App\Models\RejoinDemand;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();
    $this->paper = Paper::factory()->withQuestions()->create();
    $this->author = User::factory()->create();
    $this->session = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'author_id' => $this->author->id,
    ]);
});

test('can create a rejoin demand for an existing attempt', function () {
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);

    $response = $this->postJson("/api/attempts/{$attempt->id}/rejoin-demand");

    $response->assertCreated();
    $response->assertJsonStructure(['message', 'demand' => ['id']]);
    expect(RejoinDemand::count())->toBe(1);
    Event::assertDispatched(RejoinDemandCreated::class);
});

test('creating a second rejoin demand returns the existing one', function () {
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);
    RejoinDemand::create(['attempt_id' => $attempt->id]);

    $this->postJson("/api/attempts/{$attempt->id}/rejoin-demand");

    expect(RejoinDemand::count())->toBe(1);
});

test('cannot create a rejoin demand for a finished attempt', function () {
    $attempt = Attempt::factory()->finished()->create(['kangourou_session_id' => $this->session->id]);

    $response = $this->postJson("/api/attempts/{$attempt->id}/rejoin-demand");

    $response->assertUnprocessable();
});

test('cannot create a rejoin demand for an expired session', function () {
    $session = KangourouSession::factory()->expired()->create([
        'paper_id' => $this->paper->id,
        'author_id' => $this->author->id,
    ]);
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $session->id]);

    $response = $this->postJson("/api/attempts/{$attempt->id}/rejoin-demand");

    $response->assertForbidden();
});

test('session author can approve a rejoin demand', function () {
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);
    $demand = RejoinDemand::create(['attempt_id' => $attempt->id]);

    $response = $this->actingAs($this->author)
        ->postJson("/api/rejoin-demands/{$demand->id}/approve", ['extra_time' => 300]);

    $response->assertOk();
    expect($attempt->fresh()->extra_time)->toBe(300);
    expect($attempt->fresh()->status)->toBe('inProgress');
    expect(RejoinDemand::find($demand->id))->toBeNull();
    Event::assertDispatched(RejoinDemandResolved::class, fn ($e) => $e->resolution === 'approved');
});

test('session author can reject a rejoin demand', function () {
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);
    $demand = RejoinDemand::create(['attempt_id' => $attempt->id]);

    $response = $this->actingAs($this->author)
        ->deleteJson("/api/rejoin-demands/{$demand->id}");

    $response->assertOk();
    expect(RejoinDemand::find($demand->id))->toBeNull();
    Event::assertDispatched(RejoinDemandResolved::class, fn ($e) => $e->resolution === 'denied');
});

test('non-author cannot approve a rejoin demand', function () {
    $other = User::factory()->create();
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $this->session->id]);
    $demand = RejoinDemand::create(['attempt_id' => $attempt->id]);

    $response = $this->actingAs($other)
        ->postJson("/api/rejoin-demands/{$demand->id}/approve");

    $response->assertForbidden();
});

test('session author sees all pending rejoin demands', function () {
    $attempts = Attempt::factory()->count(2)->create(['kangourou_session_id' => $this->session->id]);
    $attempts->each(fn ($a) => RejoinDemand::create(['attempt_id' => $a->id]));

    $response = $this->actingAs($this->author)
        ->getJson('/api/my/rejoin-demands');

    $response->assertOk();
    expect($response->json('demands'))->toHaveCount(2);
});

test('logged-in user gets requires_rejoin flag when duplicate attempt', function () {
    $user = User::factory()->create();
    Attempt::factory()->withUser($user)->create(['kangourou_session_id' => $this->session->id]);

    $response = $this->actingAs($user)
        ->postJson("/api/kangourou-sessions/{$this->session->code}/attempts");

    $response->assertStatus(409);
    expect($response->json('requires_rejoin'))->toBeTrue();
    expect($response->json('attempt'))->not->toBeNull();
});

test('approve adds extra time cumulatively', function () {
    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'extra_time' => 120,
    ]);
    $demand = RejoinDemand::create(['attempt_id' => $attempt->id]);

    $this->actingAs($this->author)
        ->postJson("/api/rejoin-demands/{$demand->id}/approve", ['extra_time' => 180]);

    expect($attempt->fresh()->extra_time)->toBe(300);
});
