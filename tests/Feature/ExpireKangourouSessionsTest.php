<?php

use App\Events\RejoinDemandsDeleted;
use App\Events\SessionExpired;
use App\Jobs\ExpireKangourouSessions;
use App\Models\Attempt;
use App\Models\Division;
use App\Models\KangourouSession;
use App\Models\Paper;
use App\Models\RejoinDemand;
use App\Models\User;
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
    expect($attempt->status)->toBe('finished')
        ->and($attempt->termination)->toBe('timeout')
        ->and($attempt->score)->not->toBeNull();
});

test('job grades finished delayed attempts that were submitted before expiry', function () {
    $session = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'status' => 'active',
        'expires_at' => now()->subMinute(),
        'preferences' => ['correction' => 'delayed'],
    ]);

    $questions = $this->paper->questions()->orderByPivot('order')->get();
    $answers = Attempt::defaultAnswers();
    $answers[0] = ['answer' => $questions[0]->correct_answer, 'status' => 'answered'];

    $attempt = Attempt::factory()->finished()->create([
        'kangourou_session_id' => $session->id,
        'answers' => $answers,
        'score' => null,
        'termination' => 'submitted',
    ]);

    (new ExpireKangourouSessions)->handle(app(GradingService::class));

    $attempt->refresh();
    expect($attempt->status)->toBe('finished')
        ->and($attempt->termination)->toBe('submitted')
        ->and($attempt->score)->not->toBeNull()
        ->and($attempt->answers[0]['status'])->toBe('correct');
});

test('job does not regrade already scored attempts', function () {
    $session = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'status' => 'active',
        'expires_at' => now()->subMinute(),
        'preferences' => ['correction' => 'immediate'],
    ]);

    $answers = Attempt::defaultAnswers();
    $answers[0] = ['answer' => 'A', 'status' => 'incorrect'];

    $attempt = Attempt::factory()->finished()->create([
        'kangourou_session_id' => $session->id,
        'answers' => $answers,
        'score' => 99.0,
        'termination' => 'submitted',
    ]);

    (new ExpireKangourouSessions)->handle(app(GradingService::class));

    $attempt->refresh();
    expect($attempt->score)->toEqual(99.0)
        ->and($attempt->answers[0]['status'])->toBe('incorrect')
        ->and($attempt->termination)->toBe('submitted');
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

test('job computes analysis per division when session expires', function () {
    $division = Division::factory()->create();
    $student = User::factory()->create();
    $division->students()->attach($student->id);

    $session = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'status' => 'active',
        'expires_at' => now()->subMinute(),
    ]);
    $division->kangourouSessions()->attach($session->id);

    // Build a finished attempt with Q1 correct and the rest unanswered
    $questions = $this->paper->questions()->orderByPivot('order')->get();
    $answers = Attempt::defaultAnswers();
    $answers[0] = ['answer' => $questions[0]->correct_answer, 'status' => 'correct'];

    Attempt::factory()->finished()->create([
        'kangourou_session_id' => $session->id,
        'user_id' => $student->id,
        'answers' => $answers,
    ]);

    (new ExpireKangourouSessions)->handle(app(GradingService::class));

    $pivot = $division->kangourouSessions()->where('kangourou_session_id', $session->id)->first()->pivot;

    expect($pivot->analysis)->toBeArray()
        ->and($pivot->analysis[0]['question_id'])->toBe($questions[0]->id)
        ->and($pivot->analysis[0]['success_ratio'])->toEqual(1.0)
        ->and($pivot->analysis[0]['reviewed'])->toBeFalse()
        ->and($pivot->analysis[1]['success_ratio'])->toEqual(0.0);
});

test('job computes analysis from delayed attempts that were submitted before expiry', function () {
    $division = Division::factory()->create();
    $student = User::factory()->create();
    $division->students()->attach($student->id);

    $session = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'status' => 'active',
        'expires_at' => now()->subMinute(),
        'preferences' => ['correction' => 'delayed'],
    ]);
    $division->kangourouSessions()->attach($session->id);

    $questions = $this->paper->questions()->orderByPivot('order')->get();
    $answers = Attempt::defaultAnswers();
    $answers[0] = ['answer' => $questions[0]->correct_answer, 'status' => 'answered'];

    Attempt::factory()->finished()->create([
        'kangourou_session_id' => $session->id,
        'user_id' => $student->id,
        'answers' => $answers,
        'score' => null,
        'termination' => 'submitted',
    ]);

    (new ExpireKangourouSessions)->handle(app(GradingService::class));

    $pivot = $division->kangourouSessions()->where('kangourou_session_id', $session->id)->first()->pivot;

    expect($pivot->analysis)->toBeArray()
        ->and($pivot->analysis[0]['success_ratio'])->toEqual(1.0)
        ->and($pivot->analysis[1]['success_ratio'])->toEqual(0.0);
});

test('expiring a session deletes its rejoin demands', function () {
    $author = User::factory()->create();
    $session = KangourouSession::factory()->withAuthor($author)->create([
        'paper_id' => $this->paper->id,
        'status' => 'active',
        'expires_at' => now()->subMinute(),
    ]);
    $otherSession = KangourouSession::factory()->withAuthor($author)->create([
        'paper_id' => $this->paper->id,
        'status' => 'active',
        'expires_at' => now()->addHour(),
    ]);
    $student = User::factory()->create();
    $secondStudent = User::factory()->create();

    $attempt = Attempt::factory()->finished()->withUser($student)->create([
        'kangourou_session_id' => $session->id,
        'score' => 0,
        'termination' => 'blurred',
    ]);
    $secondAttempt = Attempt::factory()->finished()->withUser($secondStudent)->create([
        'kangourou_session_id' => $session->id,
        'score' => 0,
        'termination' => 'submitted',
    ]);
    $otherAttempt = Attempt::factory()->finished()->create([
        'kangourou_session_id' => $otherSession->id,
        'score' => 0,
        'termination' => 'blurred',
    ]);

    $demand = RejoinDemand::create(['attempt_id' => $attempt->id]);
    $secondDemand = RejoinDemand::create(['attempt_id' => $secondAttempt->id]);
    $otherDemand = RejoinDemand::create(['attempt_id' => $otherAttempt->id]);

    (new ExpireKangourouSessions)->handle(app(GradingService::class));

    expect(RejoinDemand::find($demand->id))->toBeNull()
        ->and(RejoinDemand::find($secondDemand->id))->toBeNull()
        ->and(RejoinDemand::find($otherDemand->id))->not->toBeNull();

    Event::assertDispatched(RejoinDemandsDeleted::class, function (RejoinDemandsDeleted $event) use ($author, $session, $demand, $secondDemand) {
        return $event->authorId === $author->id
            && $event->sessionId === $session->id
            && $event->demandIds === [(int) $demand->id, (int) $secondDemand->id];
    });
});

test('rejoin demands on an already expired session are deleted', function () {
    $author = User::factory()->create();
    $session = KangourouSession::factory()->expired()->withAuthor($author)->create([
        'paper_id' => $this->paper->id,
    ]);
    $attempt = Attempt::factory()->finished()->create([
        'kangourou_session_id' => $session->id,
        'score' => 0,
        'termination' => 'blurred',
    ]);
    $demand = RejoinDemand::create(['attempt_id' => $attempt->id]);

    (new ExpireKangourouSessions)->handle(app(GradingService::class));

    expect(RejoinDemand::find($demand->id))->toBeNull();
    Event::assertDispatched(RejoinDemandsDeleted::class, function (RejoinDemandsDeleted $event) use ($author, $session, $demand) {
        return $event->authorId === $author->id
            && $event->sessionId === $session->id
            && $event->demandIds === [(int) $demand->id];
    });
});
