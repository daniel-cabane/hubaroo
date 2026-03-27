<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->user = User::factory()->create([
        'email' => 'test@example.com',
    ]);
});

test('user can request password reset link', function () {
    $response = $this->postJson('/forgot-password', [
        'email' => 'test@example.com',
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['message']);

    // Verify notification was sent
    Notification::assertSentTo($this->user, \App\Notifications\ResetPasswordNotification::class);
});

test('cannot request password reset for non-existent email', function () {
    $response = $this->postJson('/forgot-password', [
        'email' => 'nonexistent@example.com',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['email']);
});

test('forgot password validates required email', function () {
    $response = $this->postJson('/forgot-password', [
        'email' => '',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['email']);
});

test('forgot password validates email format', function () {
    $response = $this->postJson('/forgot-password', [
        'email' => 'invalid-email',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['email']);
});

test('user can reset password with valid token', function () {
    $token = Password::createToken($this->user);

    $response = $this->postJson('/reset-password', [
        'token' => $token,
        'email' => 'test@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['message']);

    // Verify password was changed
    $this->assertTrue(\Hash::check('newpassword123', $this->user->fresh()->password));
});

test('cannot reset password with invalid token', function () {
    $response = $this->postJson('/reset-password', [
        'token' => 'invalid-token',
        'email' => 'test@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertUnprocessable();
});

test('cannot reset password with mismatched passwords', function () {
    $token = Password::createToken($this->user);

    $response = $this->postJson('/reset-password', [
        'token' => $token,
        'email' => 'test@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'differentpassword',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['password']);
});

test('password must be at least 8 characters', function () {
    $token = Password::createToken($this->user);

    $response = $this->postJson('/reset-password', [
        'token' => $token,
        'email' => 'test@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['password']);
});

test('reset password validates required fields', function () {
    $response = $this->postJson('/reset-password', [
        'token' => '',
        'email' => '',
        'password' => '',
        'password_confirmation' => '',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['token', 'email', 'password']);
});

test('user can login with new password after reset', function () {
    $token = Password::createToken($this->user);

    $this->postJson('/reset-password', [
        'token' => $token,
        'email' => 'test@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    // Try to login with new password
    $response = $this->postJson('/login', [
        'email' => 'test@example.com',
        'password' => 'newpassword123',
    ]);

    $response->assertOk();
    $this->assertAuthenticatedAs($this->user);
});
