<?php

use App\Events\JumpExpired;
use App\Jobs\ExpireJumps;
use App\Models\Course;
use App\Models\Division;
use App\Models\Jump;
use App\Models\JumpAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();
    $this->teacher = User::factory()->create();
    $this->division = Division::factory()->create(['teacher_id' => $this->teacher->id]);
    $this->student = User::factory()->create();
    $this->division->students()->attach($this->student->id);
    $this->course = Course::factory()->create(['division_id' => $this->division->id]);
    Question::factory()->count(5)->create(['difficulty' => 1000, 'correct_answer' => 'A']);
});

test('active jump past expiration transitions to expired after grading', function () {
    $jump = Jump::factory()->create([
        'course_id' => $this->course->id,
        'status' => 'active',
        'expiration' => now()->subMinute(),
        'nb_questions' => 1,
    ]);

    (new ExpireJumps)->handle();

    expect($jump->fresh()->status)->toBe('expired');
    Event::assertDispatched(JumpExpired::class, fn ($e) => $e->jump->id === $jump->id);
});

test('active jump not yet expired is not touched', function () {
    $jump = Jump::factory()->create([
        'course_id' => $this->course->id,
        'status' => 'active',
        'expiration' => now()->addMinutes(10),
        'nb_questions' => 1,
    ]);

    (new ExpireJumps)->handle();

    expect($jump->fresh()->status)->toBe('active');
    Event::assertNotDispatched(JumpExpired::class);
});

test('manually expiring jump is graded and expired by the job', function () {
    $jump = Jump::factory()->expiring()->create(['course_id' => $this->course->id, 'nb_questions' => 1]);

    (new ExpireJumps)->handle();

    expect($jump->fresh()->status)->toBe('expired');
    Event::assertDispatched(JumpExpired::class, fn ($e) => $e->jump->id === $jump->id);
});

test('student cannot start an attempt on an expiring jump', function () {
    $jump = Jump::factory()->expiring()->create(['course_id' => $this->course->id, 'nb_questions' => 1]);

    $response = $this->actingAs($this->student)->postJson("/api/jumps/{$jump->id}/attempts");

    $response->assertForbidden();
});

test('in-progress attempts are marked as timed out during expiry grading', function () {
    $jump = Jump::factory()->expiring()->create(['course_id' => $this->course->id, 'nb_questions' => 1]);
    $question = Question::first();
    $attempt = JumpAttempt::create([
        'jump_id' => $jump->id,
        'user_id' => $this->student->id,
        'status' => 'inProgress',
        'termination' => 'none',
        'question_list' => [['id' => $question->id, 'answer' => null, 'status' => 'pending', 'difficulty' => $question->difficulty]],
        'score' => 0,
        'timer' => 300,
    ]);

    (new ExpireJumps)->handle();

    $attempt->refresh();
    expect($attempt->status)->toBe('finished')
        ->and($attempt->termination)->toBe('timeout');
});

test('attempts are graded with correct score when jump expires', function () {
    $jump = Jump::factory()->expiring()->create(['course_id' => $this->course->id, 'nb_questions' => 1]);
    $question = Question::first();
    $attempt = JumpAttempt::create([
        'jump_id' => $jump->id,
        'user_id' => $this->student->id,
        'status' => 'finished',
        'termination' => 'submitted',
        'question_list' => [['id' => $question->id, 'answer' => 'A', 'status' => 'pending', 'difficulty' => $question->difficulty]],
        'score' => 0,
        'timer' => 0,
    ]);

    (new ExpireJumps)->handle();

    $attempt->refresh();
    expect($attempt->question_list[0]['status'])->toBe('correct')
        ->and($attempt->score)->toBe($question->difficulty);
});
