<?php

use App\Events\SessionExpired;
use App\Jobs\ExpireKangourouSessions;
use App\Models\Attempt;
use App\Models\KangourouSession;
use App\Models\Paper;
use App\Services\GradingService;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->paper = Paper::factory()->withQuestions()->create();
    Event::fake();
});

test('job expires active sessions past their expires_at', function () {
    $expired = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'status' => 'active',
        'expires_at' => now()->subMinute(),
    ]);

    $active = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'status' => 'active',
        'expires_at' => now()->addHour(),
    ]);

    (new ExpireKangourouSessions)->handle(app(GradingService::class));

    expect($expired->fresh()->status)->toBe('expired');
    expect($active->fresh()->status)->toBe('active');
});

test('job does not affect already expired sessions', function () {
    $session = KangourouSession::factory()->expired()->create([
        'paper_id' => $this->paper->id,
    ]);

    (new ExpireKangourouSessions)->handle(app(GradingService::class));

    expect($session->fresh()->status)->toBe('expired');
});

test('job does not affect draft sessions', function () {
    $session = KangourouSession::factory()->draft()->create([
        'paper_id' => $this->paper->id,
        'expires_at' => now()->subMinute(),
    ]);

    (new ExpireKangourouSessions)->handle(app(GradingService::class));

    expect($session->fresh()->status)->toBe('draft');
});

test('job auto-submits in-progress attempts when session expires', function () {
    $session = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'status' => 'active',
        'expires_at' => now()->subMinute(),
    ]);

    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $session->id,
        'status' => 'inProgress',
        'termination' => 'none',
    ]);

    (new ExpireKangourouSessions)->handle(app(GradingService::class));

    $attempt->refresh();
    expect($attempt->status)->toBe('finished');
    expect($attempt->termination)->toBe('timeout');
});

test('job broadcasts SessionExpired event for each expired session', function () {
    $session = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'status' => 'active',
        'expires_at' => now()->subMinute(),
    ]);

    (new ExpireKangourouSessions)->handle(app(GradingService::class));

    Event::assertDispatched(SessionExpired::class, fn ($e) => $e->session->id === $session->id);
});
