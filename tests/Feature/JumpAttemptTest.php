<?php

use App\Events\JumpAttemptUpdated;
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
    $this->jump = Jump::factory()->active()->create(['course_id' => $this->course->id, 'nb_questions' => 3]);

    // Create questions for the selector
    Question::factory()->count(10)->create(['difficulty' => 1000]);
});

test('student can start a jump attempt', function () {
    $response = $this->actingAs($this->student)->postJson("/api/jumps/{$this->jump->id}/attempts");

    $response->assertCreated()->assertJsonStructure(['attempt' => ['id', 'question_list', 'status']]);
    expect(JumpAttempt::where('jump_id', $this->jump->id)->where('user_id', $this->student->id)->exists())->toBeTrue();
});

test('student not in division cannot start attempt', function () {
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->postJson("/api/jumps/{$this->jump->id}/attempts");

    $response->assertForbidden();
});

test('student cannot start attempt for inactive jump', function () {
    $draftJump = Jump::factory()->create(['course_id' => $this->course->id, 'status' => 'draft']);

    $response = $this->actingAs($this->student)->postJson("/api/jumps/{$draftJump->id}/attempts");

    $response->assertForbidden();
});

test('student gets 409 with requires_rejoin when attempt already exists', function () {
    JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [],
        'score' => 0,
        'status' => 'inProgress',
        'timer' => 0,
        'extra_time' => 0,
        'termination' => 'none',
    ]);

    $response = $this->actingAs($this->student)->postJson("/api/jumps/{$this->jump->id}/attempts");

    $response->assertStatus(409)->assertJsonPath('requires_rejoin', true);
});

test('student can update an answer', function () {
    $attempt = JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => 1, 'status' => 'pending', 'answer' => null],
        ],
        'score' => 0,
        'status' => 'inProgress',
        'timer' => 0,
        'extra_time' => 0,
        'termination' => 'none',
    ]);

    $response = $this->actingAs($this->student)->patchJson("/api/jump-attempts/{$attempt->id}/answer", [
        'question_index' => 0,
        'answer' => 'A',
        'timer' => 30,
    ]);

    $response->assertOk();
    expect($attempt->fresh()->question_list[0]['answer'])->toBe('A');
});

test('student can submit attempt', function () {
    $attempt = JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [],
        'score' => 0,
        'status' => 'inProgress',
        'timer' => 100,
        'extra_time' => 0,
        'termination' => 'none',
    ]);

    $response = $this->actingAs($this->student)->postJson("/api/jump-attempts/{$attempt->id}/submit", [
        'timer' => 120,
        'termination' => 'submitted',
    ]);

    $response->assertOk();
    expect($attempt->fresh()->status)->toBe('finished');
    Event::assertDispatched(JumpAttemptUpdated::class);
});

test('student can fetch their own attempt', function () {
    $attempt = JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [],
        'score' => 0,
        'status' => 'inProgress',
        'timer' => 0,
        'extra_time' => 0,
        'termination' => 'none',
    ]);

    $response = $this->actingAs($this->student)->getJson("/api/jump-attempts/{$attempt->id}");

    $response->assertOk()->assertJsonStructure(['attempt']);
});
