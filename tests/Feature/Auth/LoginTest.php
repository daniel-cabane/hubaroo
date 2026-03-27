<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);
});

test('user can login with valid credentials', function () {
    $response = $this->postJson('/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'message',
        'user' => [
            'id',
            'name',
            'email',
        ],
    ]);
    $this->assertAuthenticatedAs($this->user);
});

test('user cannot login with invalid credentials', function () {
    $response = $this->postJson('/login', [
        'email' => 'test@example.com',
        'password' => 'invalidpassword',
    ]);

    $response->assertUnauthorized();
    $response->assertJsonStructure(['message']);
    $this->assertGuest();
});

test('user cannot login with non-existent email', function () {
    $response = $this->postJson('/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'password123',
    ]);

    $response->assertUnauthorized();
    $this->assertGuest();
});

test('login endpoint validates required fields', function () {
    $response = $this->postJson('/login', [
        'email' => '',
        'password' => '',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['email', 'password']);
});

test('login endpoint validates email format', function () {
    $response = $this->postJson('/login', [
        'email' => 'invalid-email',
        'password' => 'password123',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['email']);
});

test('authenticated user can access user endpoint', function () {
    $this->actingAs($this->user);

    $response = $this->getJson('/user');

    $response->assertOk();
    $response->assertJsonStructure([
        'user' => [
            'id',
            'name',
            'email',
        ],
    ]);
});

test('guest cannot access user endpoint', function () {
    $response = $this->getJson('/user');

    $response->assertUnauthorized();
});

test('authenticated user can check auth status', function () {
    $this->actingAs($this->user);

    $response = $this->getJson('/check');

    $response->assertOk();
    $response->assertJson([
        'authenticated' => true,
    ]);
    $response->assertJsonStructure([
        'user' => [
            'id',
            'name',
            'email',
        ],
    ]);
});

test('guest can check auth status', function () {
    $response = $this->getJson('/check');

    $response->assertOk();
    $response->assertJson([
        'authenticated' => false,
        'user' => null,
    ]);
});

test('authenticated user can logout', function () {
    $this->actingAs($this->user);

    $response = $this->postJson('/logout');

    $response->assertOk();
    $response->assertJsonStructure(['message']);
    $this->assertGuest();
});
