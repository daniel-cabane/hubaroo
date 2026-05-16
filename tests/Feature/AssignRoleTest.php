<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
});

test('user without a role can assign Teacher', function () {
    $response = $this->actingAs($this->user)->postJson('/user/role', ['role' => 'Teacher']);

    $response->assertOk()
        ->assertJsonPath('user.is_teacher', true)
        ->assertJsonPath('user.is_student', false);

    expect($this->user->fresh()->hasRole('Teacher'))->toBeTrue();
});

test('user without a role can assign Student', function () {
    $response = $this->actingAs($this->user)->postJson('/user/role', ['role' => 'Student']);

    $response->assertOk()
        ->assertJsonPath('user.is_teacher', false)
        ->assertJsonPath('user.is_student', true);

    expect($this->user->fresh()->hasRole('Student'))->toBeTrue();
});

test('user who already has Teacher cannot reassign', function () {
    $this->user->assignRole('Teacher');

    $response = $this->actingAs($this->user)->postJson('/user/role', ['role' => 'Student']);

    $response->assertUnprocessable();
    expect($this->user->fresh()->hasRole('Student'))->toBeFalse();
});

test('user who already has Student cannot reassign', function () {
    $this->user->assignRole('Student');

    $response = $this->actingAs($this->user)->postJson('/user/role', ['role' => 'Teacher']);

    $response->assertUnprocessable();
    expect($this->user->fresh()->hasRole('Teacher'))->toBeFalse();
});

test('invalid role is rejected', function () {
    $response = $this->actingAs($this->user)->postJson('/user/role', ['role' => 'Admin']);

    $response->assertUnprocessable();
});

test('guest cannot assign a role', function () {
    $response = $this->postJson('/user/role', ['role' => 'Teacher']);

    $response->assertUnauthorized();
});

test('is_student is included in user response', function () {
    $this->user->assignRole('Student');

    $response = $this->actingAs($this->user)->getJson('/user');

    $response->assertOk()
        ->assertJsonPath('user.is_student', true)
        ->assertJsonPath('user.is_teacher', false);
});
