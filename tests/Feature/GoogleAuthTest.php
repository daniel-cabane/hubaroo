<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::create(['name' => 'Student']);
});

test('redirect to google redirects the user', function () {
    Socialite::fake('google');

    $response = $this->get('/auth/google/redirect');

    $response->assertRedirect();
});

test('new google user is created with student role and logged in', function () {
    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-123',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]));

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect('/');
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'email' => 'jane@example.com',
        'google_id' => 'google-123',
    ]);
    expect(User::query()->where('email', 'jane@example.com')->first()->hasRole('Student'))->toBeTrue();
});

test('existing google user is logged in without creating a new account', function () {
    $user = User::factory()->create(['google_id' => 'google-456', 'email' => 'existing@example.com']);
    $user->assignRole('Student');

    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-456',
        'name' => $user->name,
        'email' => $user->email,
    ]));

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect('/');
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('users', 1);
});

test('existing email user gets google id linked on first google login', function () {
    $user = User::factory()->create(['email' => 'linked@example.com', 'google_id' => null]);
    $user->assignRole('Student');

    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-789',
        'name' => $user->name,
        'email' => 'linked@example.com',
    ]));

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect('/');
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->google_id)->toBe('google-789');
    $this->assertDatabaseCount('users', 1);
});
