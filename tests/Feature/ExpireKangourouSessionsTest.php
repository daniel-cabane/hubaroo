<?php

use App\Jobs\ExpireKangourouSessions;
use App\Models\KangourouSession;
use App\Models\Paper;

beforeEach(function () {
    $this->paper = Paper::factory()->withQuestions()->create();
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

    (new ExpireKangourouSessions)->handle();

    expect($expired->fresh()->status)->toBe('expired');
    expect($active->fresh()->status)->toBe('active');
});

test('job does not affect already expired sessions', function () {
    $session = KangourouSession::factory()->expired()->create([
        'paper_id' => $this->paper->id,
    ]);

    (new ExpireKangourouSessions)->handle();

    expect($session->fresh()->status)->toBe('expired');
});

test('job does not affect draft sessions', function () {
    $session = KangourouSession::factory()->draft()->create([
        'paper_id' => $this->paper->id,
        'expires_at' => now()->subMinute(),
    ]);

    (new ExpireKangourouSessions)->handle();

    expect($session->fresh()->status)->toBe('draft');
});
