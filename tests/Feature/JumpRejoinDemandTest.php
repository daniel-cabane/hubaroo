<?php

use App\Events\JumpAttemptUpdated;
use App\Events\JumpRejoinDemandCreated;
use App\Events\JumpRejoinDemandResolved;
use App\Models\Course;
use App\Models\Division;
use App\Models\Jump;
use App\Models\JumpAttempt;
use App\Models\JumpRejoinDemand;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();
    $this->teacher = User::factory()->create();
    $this->division = Division::factory()->create(['teacher_id' => $this->teacher->id]);
    $this->student = User::factory()->create();
    $this->division->students()->attach($this->student->id);
    $this->course = Course::factory()->create(['division_id' => $this->division->id]);
    $this->jump = Jump::factory()->active()->create(['course_id' => $this->course->id]);
    $this->attempt = JumpAttempt::create([
        'jump_id' => $this->jump->id,
        'user_id' => $this->student->id,
        'question_list' => [],
        'score' => 0,
        'status' => 'finished',
        'timer' => 120,
        'extra_time' => 0,
        'termination' => 'blurred',
    ]);
});

test('student can create a jump rejoin demand', function () {
    $response = $this->actingAs($this->student)->postJson("/api/jump-attempts/{$this->attempt->id}/rejoin-demand");

    $response->assertCreated()->assertJsonStructure(['message', 'demand' => ['id']]);
    expect(JumpRejoinDemand::where('jump_attempt_id', $this->attempt->id)->exists())->toBeTrue();
    Event::assertDispatched(JumpRejoinDemandCreated::class);
});

test('student cannot create a rejoin demand for another users attempt', function () {
    $other = User::factory()->create();

    $response = $this->actingAs($other)->postJson("/api/jump-attempts/{$this->attempt->id}/rejoin-demand");

    $response->assertForbidden();
});

test('teacher can approve a jump rejoin demand', function () {
    $demand = JumpRejoinDemand::create(['jump_attempt_id' => $this->attempt->id]);

    $response = $this->actingAs($this->teacher)->postJson("/api/jump-rejoin-demands/{$demand->id}/approve", [
        'extra_time' => 60,
    ]);

    $response->assertOk();
    expect($this->attempt->fresh()->status)->toBe('inProgress');
    expect($this->attempt->fresh()->termination)->toBe('none');
    expect($this->attempt->fresh()->extra_time)->toBe(60);
    expect($this->attempt->fresh()->timer)->toBe(180); // 120 (remaining at submit) + 60 (extra granted)
    expect(JumpRejoinDemand::find($demand->id))->toBeNull();
    Event::assertDispatched(JumpRejoinDemandResolved::class, fn ($e) => $e->resolution === 'approved');
    Event::assertDispatched(JumpAttemptUpdated::class);
});

test('teacher can reject a jump rejoin demand', function () {
    $demand = JumpRejoinDemand::create(['jump_attempt_id' => $this->attempt->id]);

    $response = $this->actingAs($this->teacher)->deleteJson("/api/jump-rejoin-demands/{$demand->id}");

    $response->assertOk();
    expect(JumpRejoinDemand::find($demand->id))->toBeNull();
    Event::assertDispatched(JumpRejoinDemandResolved::class, fn ($e) => $e->resolution === 'denied');
});

test('non-teacher cannot approve a jump rejoin demand', function () {
    $demand = JumpRejoinDemand::create(['jump_attempt_id' => $this->attempt->id]);
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->postJson("/api/jump-rejoin-demands/{$demand->id}/approve");

    $response->assertForbidden();
    expect($this->attempt->fresh()->status)->toBe('finished');
});

test('teacher myIndex returns demands with correct structure including answered_count and rank', function () {
    $questionList = [
        ['id' => 1, 'answer' => 'A', 'status' => 'pending', 'difficulty' => 1],
        ['id' => 2, 'answer' => 'B', 'status' => 'pending', 'difficulty' => 1],
        ['id' => 3, 'answer' => null, 'status' => 'pending', 'difficulty' => 1],
    ];
    $this->attempt->update(['question_list' => $questionList]);
    JumpRejoinDemand::create(['jump_attempt_id' => $this->attempt->id]);

    $response = $this->actingAs($this->teacher)->getJson('/api/my/jump-rejoin-demands');

    $response->assertOk();
    $demand = $response->json('demands.0');
    expect($demand['attempt']['answered_count'])->toBe(2);
    expect($demand['attempt']['jump']['rank'])->toBe(1);
    expect($demand['attempt']['timer'])->toBe(120);
    expect($demand['attempt']['user']['name'])->toBe($this->student->name);
});
