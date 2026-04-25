<?php

use App\Models\BugReport;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::create(['name' => 'Admin']);
    Role::create(['name' => 'Teacher']);
    Role::create(['name' => 'Student']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->user = User::factory()->create();
    $this->user->assignRole('Student');
});

test('guest cannot submit a bug report', function () {
    $response = $this->postJson('/api/bug-reports', ['comment' => 'Something is broken']);

    $response->assertUnauthorized();
});

test('authenticated user can submit a bug report', function () {
    $response = $this->actingAs($this->user)->postJson('/api/bug-reports', [
        'comment' => 'The login button does not work.',
    ]);

    $response->assertCreated();
    expect($response->json('bug_report.comment'))->toBe('The login button does not work.');
    expect($response->json('bug_report.status'))->toBe('new');
    expect(BugReport::count())->toBe(1);
});

test('bug report comment is required', function () {
    $response = $this->actingAs($this->user)->postJson('/api/bug-reports', ['comment' => '']);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('comment');
});

test('user cannot submit more than 5 unsolved bug reports', function () {
    BugReport::factory()->count(5)->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user)->postJson('/api/bug-reports', [
        'comment' => 'Another bug',
    ]);

    $response->assertUnprocessable();
});

test('user can submit again after a report is resolved', function () {
    BugReport::factory()->count(4)->create(['user_id' => $this->user->id]);
    BugReport::factory()->fixed()->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user)->postJson('/api/bug-reports', [
        'comment' => 'A new bug',
    ]);

    $response->assertCreated();
});

test('user can get unsolved bug report count', function () {
    BugReport::factory()->count(3)->create(['user_id' => $this->user->id]);
    BugReport::factory()->fixed()->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user)->getJson('/api/my/bug-reports/unsolved-count');

    $response->assertOk();
    expect($response->json('count'))->toBe(3);
});

test('guest cannot get unsolved count', function () {
    $response = $this->getJson('/api/my/bug-reports/unsolved-count');

    $response->assertUnauthorized();
});

test('non-admin cannot list bug reports', function () {
    $response = $this->actingAs($this->user)->getJson('/api/admin/bug-reports');

    $response->assertForbidden();
});

test('admin can list bug reports', function () {
    BugReport::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/admin/bug-reports');

    $response->assertOk();
    expect($response->json('bug_reports'))->toHaveCount(3);
});

test('admin can update bug report status', function () {
    $bugReport = BugReport::factory()->create(['status' => 'new']);

    $response = $this->actingAs($this->admin)->patchJson("/api/admin/bug-reports/{$bugReport->id}", [
        'status' => 'fixed',
    ]);

    $response->assertOk();
    expect($response->json('bug_report.status'))->toBe('fixed');
    expect($bugReport->fresh()->status)->toBe('fixed');
});

test('admin cannot set invalid status', function () {
    $bugReport = BugReport::factory()->create();

    $response = $this->actingAs($this->admin)->patchJson("/api/admin/bug-reports/{$bugReport->id}", [
        'status' => 'banana',
    ]);

    $response->assertUnprocessable();
});

test('admin can delete bug report', function () {
    $bugReport = BugReport::factory()->create();

    $response = $this->actingAs($this->admin)->deleteJson("/api/admin/bug-reports/{$bugReport->id}");

    $response->assertOk();
    expect(BugReport::count())->toBe(0);
});

test('non-admin cannot delete bug report', function () {
    $bugReport = BugReport::factory()->create();

    $response = $this->actingAs($this->user)->deleteJson("/api/admin/bug-reports/{$bugReport->id}");

    $response->assertForbidden();
    expect(BugReport::count())->toBe(1);
});
