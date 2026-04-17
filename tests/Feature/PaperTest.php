<?php

use App\Models\Paper;

test('can fetch a paper with questions without answers', function () {
    $paper = Paper::factory()->withQuestions()->create();

    $response = $this->getJson("/api/papers/{$paper->id}");

    $response->assertOk();
    $response->assertJsonPath('paper.id', $paper->id);
    $response->assertJsonPath('paper.title', $paper->title);
    $response->assertJsonCount(26, 'paper.questions');

    // Ensure correct_answer is not exposed
    $questions = $response->json('paper.questions');
    foreach ($questions as $question) {
        expect($question)->not->toHaveKey('correct_answer');
        expect($question)->toHaveKeys(['id', 'image', 'tier']);
    }
});

test('paper questions are ordered', function () {
    $paper = Paper::factory()->withQuestions()->create();

    $response = $this->getJson("/api/papers/{$paper->id}");

    $questions = $response->json('paper.questions');
    $orders = array_column($questions, 'pivot');
    $orderValues = array_column($orders, 'order');

    expect($orderValues)->toEqual(range(1, 26));
});

test('returns 404 for nonexistent paper', function () {
    $response = $this->getJson('/api/papers/9999');

    $response->assertNotFound();
});
