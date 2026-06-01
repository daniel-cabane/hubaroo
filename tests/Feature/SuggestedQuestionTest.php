<?php

use App\Models\Course;
use App\Models\Division;
use App\Models\SuggestedQuestion;
use App\Models\User;

beforeEach(function () {
    $this->teacher = User::factory()->create();
    $this->division = Division::factory()->create(['teacher_id' => $this->teacher->id]);
    $this->student = User::factory()->create();
    $this->division->students()->attach($this->student->id);
    $this->course = Course::factory()->create(['division_id' => $this->division->id]);
});

// --- Teacher: index ---

test('teacher can list suggested questions for their course', function () {
    SuggestedQuestion::factory()->count(3)->create(['course_id' => $this->course->id]);

    $response = $this->actingAs($this->teacher)
        ->getJson("/api/courses/{$this->course->id}/suggested-questions");

    $response->assertOk()->assertJsonCount(3, 'suggested_questions');
});

test('non-teacher cannot list suggested questions', function () {
    $response = $this->actingAs($this->student)
        ->getJson("/api/courses/{$this->course->id}/suggested-questions");

    $response->assertForbidden();
});

// --- Student: publicForDivision ---

test('student in division can fetch public suggested questions', function () {
    SuggestedQuestion::factory()->public()->create(['course_id' => $this->course->id]);
    SuggestedQuestion::factory()->create(['course_id' => $this->course->id]); // private

    $response = $this->actingAs($this->student)
        ->getJson("/api/divisions/{$this->division->id}/public-suggested-questions");

    $response->assertOk()->assertJsonCount(1, 'suggested_questions');
});

test('student not in division cannot fetch public suggested questions', function () {
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)
        ->getJson("/api/divisions/{$this->division->id}/public-suggested-questions");

    $response->assertForbidden();
});

test('teacher cannot fetch public suggested questions for division', function () {
    $response = $this->actingAs($this->teacher)
        ->getJson("/api/divisions/{$this->division->id}/public-suggested-questions");

    $response->assertForbidden();
});

// --- Teacher: togglePublic ---

test('teacher can toggle a suggested question to public', function () {
    $sq = SuggestedQuestion::factory()->create(['course_id' => $this->course->id, 'is_public' => false]);

    $response = $this->actingAs($this->teacher)
        ->patchJson("/api/suggested-questions/{$sq->id}/toggle-public");

    $response->assertOk();
    expect($sq->fresh()->is_public)->toBeTrue();
});

test('teacher can toggle a public suggested question back to private', function () {
    $sq = SuggestedQuestion::factory()->public()->create(['course_id' => $this->course->id]);

    $response = $this->actingAs($this->teacher)
        ->patchJson("/api/suggested-questions/{$sq->id}/toggle-public");

    $response->assertOk();
    expect($sq->fresh()->is_public)->toBeFalse();
});

test('non-teacher cannot toggle suggested question visibility', function () {
    $sq = SuggestedQuestion::factory()->create(['course_id' => $this->course->id]);

    $response = $this->actingAs($this->student)
        ->patchJson("/api/suggested-questions/{$sq->id}/toggle-public");

    $response->assertForbidden();
});

// --- Teacher: destroy ---

test('teacher can delete a suggested question', function () {
    $sq = SuggestedQuestion::factory()->create(['course_id' => $this->course->id]);

    $response = $this->actingAs($this->teacher)
        ->deleteJson("/api/suggested-questions/{$sq->id}");

    $response->assertOk();
    expect(SuggestedQuestion::find($sq->id))->toBeNull();
});

test('non-teacher cannot delete a suggested question', function () {
    $sq = SuggestedQuestion::factory()->create(['course_id' => $this->course->id]);

    $response = $this->actingAs($this->student)
        ->deleteJson("/api/suggested-questions/{$sq->id}");

    $response->assertForbidden();
    expect(SuggestedQuestion::find($sq->id))->not->toBeNull();
});

// --- Student: random ---

test('student can fetch a random question using their mastery', function () {
    \App\Models\Question::factory()->count(5)->create(['difficulty' => 600]);

    $this->student->update(['mastery' => 500]);

    $response = $this->actingAs($this->student)
        ->getJson('/api/random-question');

    $response->assertOk()
        ->assertJsonStructure(['question' => ['id', 'image', 'correct_answer', 'difficulty'], 'reference']);

    expect($response->json('reference'))->toBe(500);
});

test('student can fetch a random question with a custom reference', function () {
    \App\Models\Question::factory()->count(5)->create(['difficulty' => 900]);

    $response = $this->actingAs($this->student)
        ->getJson('/api/random-question?reference=800');

    $response->assertOk();
    expect($response->json('reference'))->toBe(800);
    expect($response->json('question.difficulty'))->toBeGreaterThanOrEqual(800);
});

test('unauthenticated user cannot fetch a random question', function () {
    $response = $this->getJson('/api/random-question');

    $response->assertUnauthorized();
});
