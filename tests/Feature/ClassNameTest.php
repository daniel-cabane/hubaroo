<?php

use App\Models\Division;
use App\Models\KangourouSession;
use App\Models\Paper;
use App\Models\User;
use App\Services\ClassNameFormatter;

// --- ClassNameFormatter unit-level tests ---

test('formats simple first and last name', function () {
    expect(ClassNameFormatter::format('leo', 'dicap'))->toBe('Leo DICAP');
});

test('formats multi-word first name with hyphens', function () {
    expect(ClassNameFormatter::format('jean PAUL', 'La Glu de Colle'))->toBe('Jean-Paul LA GLU DE COLLE');
});

test('normalises mixed case in first name', function () {
    expect(ClassNameFormatter::format('MARIE claire', 'dupont'))->toBe('Marie-Claire DUPONT');
});

test('formats last name entirely in uppercase', function () {
    expect(ClassNameFormatter::format('alice', 'martin'))->toBe('Alice MARTIN');
});

test('correctly capitalises accented first-name letters', function () {
    expect(ClassNameFormatter::format('élie', 'dupont'))->toBe('Élie DUPONT');
});

test('correctly uppercases accented last-name letters', function () {
    expect(ClassNameFormatter::format('anne', 'élise de la côte'))->toBe('Anne ÉLISE DE LA CÔTE');
});

// --- Join division with class name ---

test('student must provide first and last name to join a class', function () {
    $user = User::factory()->create();
    $division = Division::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/divisions/join', [
        'code' => $division->code,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['first_name', 'last_name']);
});

test('joining a class saves formatted class name in pivot', function () {
    $user = User::factory()->create();
    $division = Division::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/divisions/join', [
        'code' => $division->code,
        'first_name' => 'jean PAUL',
        'last_name' => 'La Glu de Colle',
    ]);

    $response->assertOk();

    $pivot = $division->students()->where('users.id', $user->id)->first()?->pivot;
    expect($pivot->class_name)->toBe('Jean-Paul LA GLU DE COLLE');
});

// --- Teacher updates student class name ---

test('teacher can update student class name', function () {
    $teacher = User::factory()->create();
    $division = Division::factory()->create(['teacher_id' => $teacher->id]);
    $student = User::factory()->create();
    $division->students()->attach($student->id, ['class_name' => 'Old Name']);

    $response = $this->actingAs($teacher)->patchJson("/api/divisions/{$division->id}/students/{$student->id}", [
        'first_name' => 'marie claire',
        'last_name' => 'dupont',
    ]);

    $response->assertOk();
    expect($response->json('class_name'))->toBe('Marie-Claire DUPONT');

    $pivot = $division->students()->where('users.id', $student->id)->first()?->pivot;
    expect($pivot->class_name)->toBe('Marie-Claire DUPONT');
});

test('non-teacher cannot update student class name', function () {
    $teacher = User::factory()->create();
    $division = Division::factory()->create(['teacher_id' => $teacher->id]);
    $student = User::factory()->create();
    $division->students()->attach($student->id);

    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser)->patchJson("/api/divisions/{$division->id}/students/{$student->id}", [
        'first_name' => 'jean',
        'last_name' => 'dupont',
    ]);

    $response->assertForbidden();
});

// --- Attempt name uses class name ---

test('attempt name uses class name when user is in a session division', function () {
    $user = User::factory()->create();
    $paper = Paper::factory()->withQuestions()->create();
    $session = KangourouSession::factory()->create(['paper_id' => $paper->id]);
    $division = Division::factory()->create();
    $division->students()->attach($user->id, ['class_name' => 'Jean-Paul LA GLU DE COLLE']);
    $session->divisions()->attach($division->id);

    $response = $this->actingAs($user)->postJson("/api/kangourou-sessions/{$session->code}/attempts");

    $response->assertCreated();
    expect($response->json('attempt.name'))->toBe('Jean-Paul LA GLU DE COLLE');
});

test('attempt name falls back to user name when no class name in pivot', function () {
    $user = User::factory()->create(['name' => 'Alice Martin']);
    $paper = Paper::factory()->withQuestions()->create();
    $session = KangourouSession::factory()->create(['paper_id' => $paper->id]);
    $division = Division::factory()->create();
    $division->students()->attach($user->id, ['class_name' => null]);
    $session->divisions()->attach($division->id);

    $response = $this->actingAs($user)->postJson("/api/kangourou-sessions/{$session->code}/attempts");

    $response->assertCreated();
    expect($response->json('attempt.name'))->toBe('Alice Martin');
});

test('attempt name uses user account name when not in any division', function () {
    $user = User::factory()->create(['name' => 'Regular User']);
    $paper = Paper::factory()->withQuestions()->create();
    $session = KangourouSession::factory()->create(['paper_id' => $paper->id]);

    $response = $this->actingAs($user)->postJson("/api/kangourou-sessions/{$session->code}/attempts");

    $response->assertCreated();
    expect($response->json('attempt.name'))->toBe('Regular User');
});
