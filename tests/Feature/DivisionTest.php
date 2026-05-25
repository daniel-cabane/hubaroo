<?php

use App\Models\Course;
use App\Models\Division;
use App\Models\Jump;
use App\Models\JumpAttempt;
use App\Models\User;

beforeEach(function () {
    $this->teacher = User::factory()->create();
    $this->division = Division::factory()->create(['teacher_id' => $this->teacher->id]);
    $this->student = User::factory()->create();
    $this->division->students()->attach($this->student->id);
    $this->course = Course::factory()->create(['division_id' => $this->division->id]);
});

test('student myIndex returns active jumps without an attempt', function () {
    $jump = Jump::factory()->active()->create(['course_id' => $this->course->id]);

    $response = $this->actingAs($this->student)->getJson('/api/my/divisions');

    $response->assertOk();
    $activeJumps = collect($response->json('divisions.0.active_jumps'));
    expect($activeJumps->pluck('id'))->toContain($jump->id);
});

test('student myIndex excludes active jumps the student already has an attempt for', function () {
    $jump = Jump::factory()->active()->create(['course_id' => $this->course->id]);
    JumpAttempt::create(['jump_id' => $jump->id, 'user_id' => $this->student->id]);

    $response = $this->actingAs($this->student)->getJson('/api/my/divisions');

    $response->assertOk();
    $activeJumps = collect($response->json('divisions.0.active_jumps'));
    expect($activeJumps->pluck('id'))->not->toContain($jump->id);
});

test('student myIndex still returns jump for students who have no attempt', function () {
    $otherStudent = User::factory()->create();
    $this->division->students()->attach($otherStudent->id);
    $jump = Jump::factory()->active()->create(['course_id' => $this->course->id]);
    JumpAttempt::create(['jump_id' => $jump->id, 'user_id' => $otherStudent->id]);

    $response = $this->actingAs($this->student)->getJson('/api/my/divisions');

    $response->assertOk();
    $activeJumps = collect($response->json('divisions.0.active_jumps'));
    expect($activeJumps->pluck('id'))->toContain($jump->id);
});
