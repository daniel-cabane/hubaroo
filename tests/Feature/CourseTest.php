<?php

use App\Events\JumpActivated;
use App\Models\Course;
use App\Models\Division;
use App\Models\Jump;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();
    $this->teacher = User::factory()->create();
    $this->division = Division::factory()->create(['teacher_id' => $this->teacher->id]);
    $this->student = User::factory()->create();
    $this->division->students()->attach($this->student->id);
    $this->course = Course::factory()->create(['division_id' => $this->division->id]);
});

test('teacher can list courses for their division', function () {
    $response = $this->actingAs($this->teacher)->getJson("/api/divisions/{$this->division->id}/courses");

    $response->assertOk()->assertJsonStructure(['courses']);
});

test('student can list non-archived courses', function () {
    Course::factory()->archived()->create(['division_id' => $this->division->id]);

    $response = $this->actingAs($this->student)->getJson("/api/divisions/{$this->division->id}/courses");

    $response->assertOk();
    $courses = $response->json('courses');
    $activeCourses = collect($courses)->where('archived', false);
    expect($activeCourses)->toHaveCount(1);
});

test('teacher can create a course', function () {
    $response = $this->actingAs($this->teacher)->postJson("/api/divisions/{$this->division->id}/courses", [
        'title' => 'Mon parcours de test',
    ]);

    $response->assertCreated()->assertJsonPath('course.title', 'Mon parcours de test');
    expect(Course::where('title', 'Mon parcours de test')->exists())->toBeTrue();
});

test('student cannot create a course', function () {
    $response = $this->actingAs($this->student)->postJson("/api/divisions/{$this->division->id}/courses", [
        'title' => 'Parcours non autorisé',
    ]);

    $response->assertForbidden();
});

test('teacher can archive a course', function () {
    $response = $this->actingAs($this->teacher)->patchJson("/api/courses/{$this->course->id}", [
        'archived' => true,
    ]);

    $response->assertOk();
    expect($this->course->fresh()->archived)->toBeTrue();
});

test('teacher can delete a course with no jumps', function () {
    $response = $this->actingAs($this->teacher)->deleteJson("/api/courses/{$this->course->id}");

    $response->assertOk();
    expect(Course::find($this->course->id))->toBeNull();
});

test('teacher cannot delete a course that has jumps', function () {
    Jump::factory()->create(['course_id' => $this->course->id]);

    $response = $this->actingAs($this->teacher)->deleteJson("/api/courses/{$this->course->id}");

    $response->assertStatus(422);
    expect(Course::find($this->course->id))->not->toBeNull();
});

test('teacher can get course details', function () {
    Jump::factory()->active()->create(['course_id' => $this->course->id]);

    $response = $this->actingAs($this->teacher)->getJson("/api/courses/{$this->course->id}/details");

    $response->assertOk()->assertJsonStructure(['course', 'jumps', 'students']);
});

test('teacher can create a jump', function () {
    $response = $this->actingAs($this->teacher)->postJson("/api/courses/{$this->course->id}/jumps", [
        'nb_questions' => 5,
        'time' => 10,
        'growth' => 2,
        'status' => 'draft',
    ]);

    $response->assertCreated()->assertJsonPath('jump.nb_questions', 5);
    expect(Jump::where('course_id', $this->course->id)->exists())->toBeTrue();
});

test('creating an active jump sets expiration and broadcasts', function () {
    $response = $this->actingAs($this->teacher)->postJson("/api/courses/{$this->course->id}/jumps", [
        'nb_questions' => 7,
        'time' => 15,
        'growth' => 3,
        'status' => 'active',
    ]);

    $response->assertCreated();
    $jump = Jump::first();
    expect($jump->expiration)->not->toBeNull();
    Event::assertDispatched(JumpActivated::class);
});

test('teacher can activate a draft jump', function () {
    $jump = Jump::factory()->create(['course_id' => $this->course->id, 'status' => 'draft']);

    $response = $this->actingAs($this->teacher)->patchJson("/api/jumps/{$jump->id}", [
        'status' => 'active',
    ]);

    $response->assertOk();
    expect($jump->fresh()->status)->toBe('active');
    Event::assertDispatched(JumpActivated::class);
});

test('teacher can delete a jump', function () {
    $jump = Jump::factory()->create(['course_id' => $this->course->id]);

    $response = $this->actingAs($this->teacher)->deleteJson("/api/jumps/{$jump->id}");

    $response->assertOk();
    expect(Jump::find($jump->id))->toBeNull();
});
