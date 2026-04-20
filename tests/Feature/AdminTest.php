<?php

use App\Models\Paper;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::create(['name' => 'Admin']);
    Role::create(['name' => 'Teacher']);
    Role::create(['name' => 'Student']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->teacher = User::factory()->create();
    $this->teacher->assignRole('Teacher');
});

test('non-admin cannot access admin papers', function () {
    $response = $this->actingAs($this->teacher)->getJson('/api/admin/papers');

    $response->assertForbidden();
});

test('admin can list papers', function () {
    Paper::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/admin/papers');

    $response->assertOk();
    expect($response->json('papers'))->toHaveCount(3);
});

test('admin can update a paper', function () {
    $paper = Paper::factory()->create(['title' => 'Old Title', 'year' => 2020, 'level' => 'e']);

    $response = $this->actingAs($this->admin)->patchJson("/api/admin/papers/{$paper->id}", [
        'title' => 'New Title',
        'year' => 2021,
        'level' => 'b',
    ]);

    $response->assertOk();
    expect($response->json('paper.title'))->toBe('New Title');
    expect($response->json('paper.year'))->toBe(2021);
    expect($response->json('paper.level'))->toBe('b');
});

test('admin can search users', function () {
    User::factory()->create(['name' => 'Alice Testing', 'email' => 'alice@test.com']);

    $response = $this->actingAs($this->admin)->getJson('/api/admin/users?q=Alice');

    $response->assertOk();
    expect($response->json('users'))->toHaveCount(1);
    expect($response->json('users.0.name'))->toBe('Alice Testing');
});

test('admin can change user role', function () {
    $student = User::factory()->create();
    $student->assignRole('Student');

    $response = $this->actingAs($this->admin)->patchJson("/api/admin/users/{$student->id}/role", [
        'role' => 'Teacher',
    ]);

    $response->assertOk();
    expect($response->json('user.roles'))->toContain('Teacher');
    expect($student->fresh()->hasRole('Teacher'))->toBeTrue();
});

test('admin can list roles', function () {
    $response = $this->actingAs($this->admin)->getJson('/api/admin/roles');

    $response->assertOk();
    expect($response->json('roles'))->toContain('Admin');
    expect($response->json('roles'))->toContain('Teacher');
    expect($response->json('roles'))->toContain('Student');
});

test('guest cannot access admin routes', function () {
    $response = $this->getJson('/api/admin/papers');

    $response->assertUnauthorized();
});
