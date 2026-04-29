<?php

use App\Models\User;

test('authenticated user can update their name', function () {
    $user = User::factory()->create(['name' => 'Old Name']);

    $response = $this->actingAs($user)->patchJson('/user/name', ['name' => 'New Name']);

    $response->assertOk()
        ->assertJsonPath('user.name', 'New Name');

    expect($user->fresh()->name)->toBe('New Name');
});

test('name must be at least 2 characters', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patchJson('/user/name', ['name' => 'A']);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('name is required', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patchJson('/user/name', ['name' => '']);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('unauthenticated user cannot update name', function () {
    $response = $this->patchJson('/user/name', ['name' => 'Hacker']);

    $response->assertUnauthorized();
});
