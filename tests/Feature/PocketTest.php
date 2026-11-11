<?php

use App\Events\JumpActivated;
use App\Events\JumpExpired;
use App\Events\PocketChanged;
use App\Events\SessionExpired;
use App\Jobs\ExpireJumps;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Division;
use App\Models\DivisionInvite;
use App\Models\Jump;
use App\Models\JumpAttempt;
use App\Models\KangourouSession;
use App\Models\Paper;
use App\Models\SuggestedQuestion;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->teacher = User::factory()->create();
    $this->division = Division::factory()->create([
        'teacher_id' => $this->teacher->id,
        'name' => 'Alpha',
    ]);
    $this->student = User::factory()->create();
    $this->division->students()->attach($this->student->id);
    $this->course = Course::factory()->create([
        'division_id' => $this->division->id,
        'title' => 'Fractions',
    ]);
});

test('guests cannot read the pocket', function () {
    $this->getJson('/api/my/pocket')->assertUnauthorized();
});

test('classes are alphabetical and empty classes are included', function () {
    Division::factory()->create([
        'teacher_id' => $this->teacher->id,
        'name' => 'musee',
    ])->students()->attach($this->student->id);
    $this->division->update(['name' => 'Zebra']);

    $names = $this->actingAs($this->student)->getJson('/api/my/pocket')->json('classes.*.name');

    expect($names)->toBe(['musee', 'Zebra']);
});

test('archived divisions and archived courses are absent', function () {
    $archived = Division::factory()->create([
        'teacher_id' => $this->teacher->id,
        'name' => 'Old',
        'archived' => true,
    ]);
    $archived->students()->attach($this->student->id);
    $hidden = Course::factory()->archived()->create([
        'division_id' => $this->division->id,
        'title' => 'Hidden',
    ]);
    Jump::factory()->active()->create(['course_id' => $hidden->id]);
    SuggestedQuestion::factory()->public()->create(['course_id' => $hidden->id]);

    $response = $this->actingAs($this->student)->getJson('/api/my/pocket');

    $response->assertOk();
    expect($response->json('classes'))->toHaveCount(1)
        ->and($response->json('classes.0.open_jumps'))->toBe([])
        ->and($response->json('classes.0.open_questions'))->toBe([])
        ->and($response->json('classes.0.active_course'))->toBeNull();
});

test('open jumps follow start resume and closed rules', function () {
    $start = Jump::factory()->active()->create(['course_id' => $this->course->id, 'nb_questions' => 8, 'time' => 12]);
    $resume = Jump::factory()->active()->create(['course_id' => $this->course->id]);
    JumpAttempt::create([
        'jump_id' => $resume->id,
        'user_id' => $this->student->id,
        'status' => 'inProgress',
    ]);
    $finished = Jump::factory()->active()->create(['course_id' => $this->course->id]);
    JumpAttempt::create([
        'jump_id' => $finished->id,
        'user_id' => $this->student->id,
        'status' => 'finished',
        'score' => 4,
    ]);
    Jump::factory()->create(['course_id' => $this->course->id, 'status' => 'draft']);
    Jump::factory()->expiring()->create(['course_id' => $this->course->id]);
    Jump::factory()->expired()->create(['course_id' => $this->course->id]);
    Jump::factory()->create([
        'course_id' => $this->course->id,
        'status' => 'active',
        'expiration' => now()->subMinute(),
    ]);

    $jumps = collect($this->actingAs($this->student)->getJson('/api/my/pocket')->json('classes.0.open_jumps'));

    expect($jumps->pluck('id')->all())->toBe([$resume->id, $start->id])
        ->and($jumps->firstWhere('id', $start->id)['action'])->toBe('start')
        ->and($jumps->firstWhere('id', $resume->id)['action'])->toBe('resume')
        ->and($jumps->firstWhere('id', $start->id))->not->toHaveKey('question_list');
});

test('open sessions and a session linked to two classes', function () {
    $other = Division::factory()->create(['teacher_id' => $this->teacher->id, 'name' => 'Beta']);
    $other->students()->attach($this->student->id);
    $paper = Paper::factory()->create(['title' => 'Sujet 2024']);
    $join = KangourouSession::factory()->create(['paper_id' => $paper->id]);
    $join->divisions()->attach([$this->division->id, $other->id]);
    $resume = KangourouSession::factory()->create(['paper_id' => $paper->id]);
    $resume->divisions()->attach($this->division->id);
    Attempt::factory()->create([
        'kangourou_session_id' => $resume->id,
        'user_id' => $this->student->id,
        'status' => 'inProgress',
    ]);
    $done = KangourouSession::factory()->create(['paper_id' => $paper->id]);
    $done->divisions()->attach($this->division->id);
    Attempt::factory()->finished()->create([
        'kangourou_session_id' => $done->id,
        'user_id' => $this->student->id,
        'score' => 10,
    ]);
    $unlinked = KangourouSession::factory()->create();
    Attempt::factory()->finished()->create([
        'kangourou_session_id' => $unlinked->id,
        'user_id' => $this->student->id,
        'score' => 99,
    ]);

    $response = $this->actingAs($this->student)->getJson('/api/my/pocket');
    $alpha = collect($response->json('classes'))->firstWhere('name', 'Alpha');
    $beta = collect($response->json('classes'))->firstWhere('name', 'Beta');
    $open = collect($alpha['open_sessions']);

    expect($open->pluck('id')->all())->toEqualCanonicalizing([$join->id, $resume->id])
        ->and($open->firstWhere('id', $join->id)['action'])->toBe('join')
        ->and($open->firstWhere('id', $join->id)['paper_title'])->toBe('Sujet 2024')
        ->and($open->firstWhere('id', $resume->id)['action'])->toBe('resume')
        ->and(collect($beta['open_sessions'])->pluck('id')->all())->toBe([$join->id]);

    $allSessionIds = collect($response->json('classes'))
        ->flatMap(fn (array $class) => collect($class['open_sessions'])->pluck('id')->merge(collect($class['recent_sessions'])->pluck('id')));

    expect($allSessionIds)->not->toContain($unlinked->id);
});

test('active course score matches graded jumps and the recap is the last three', function () {
    $older = Course::factory()->create(['division_id' => $this->division->id, 'title' => 'Older']);
    Jump::factory()->expired()->create(['course_id' => $older->id]);
    Jump::factory()->active()->create(['course_id' => $older->id]);
    $first = Jump::factory()->expired()->create(['course_id' => $this->course->id]);
    $second = Jump::factory()->expired()->create(['course_id' => $this->course->id]);
    $third = Jump::factory()->expired()->create(['course_id' => $this->course->id]);
    $fourth = Jump::factory()->expired()->create(['course_id' => $this->course->id]);
    Jump::factory()->expired()->create(['course_id' => $this->course->id]);
    Jump::factory()->active()->create(['course_id' => $this->course->id]);

    foreach ([$first, $second, $third, $fourth] as $index => $jump) {
        JumpAttempt::create([
            'jump_id' => $jump->id,
            'user_id' => $this->student->id,
            'status' => 'finished',
            'score' => ($index + 1) * 10,
            'updated_at' => now()->subDays(3),
        ]);
    }

    $course = $this->actingAs($this->student)->getJson('/api/my/pocket')->json('classes.0.active_course');
    $recent = collect($course['recent_jumps']);

    expect($course['id'])->toBe($this->course->id)
        ->and($course['score'])->toBe(100)
        ->and($recent)->toHaveCount(3)
        ->and($recent->pluck('id')->all())->toBe([$fourth->id, $third->id, $second->id])
        ->and($recent->firstWhere('id', $fourth->id)['number'])->toBe(4)
        ->and($recent->firstWhere('id', $fourth->id)['score'])->toBe(40);
});

test('a fresh grade on another course is not dropped', function () {
    $other = Course::factory()->create(['division_id' => $this->division->id, 'title' => 'Geometrie']);
    $fresh = Jump::factory()->expired()->create(['course_id' => $other->id]);
    $current = Jump::factory()->active()->create(['course_id' => $this->course->id]);
    JumpAttempt::create([
        'jump_id' => $fresh->id,
        'user_id' => $this->student->id,
        'status' => 'finished',
        'score' => 7,
        'updated_at' => now()->subHour(),
    ]);

    $payload = $this->actingAs($this->student)->getJson('/api/my/pocket')->json('classes.0');

    expect($payload['active_course']['id'])->toBe($this->course->id)
        ->and($payload['active_course']['recent_jumps'])->toBe([])
        ->and($payload['fresh_grades'][0]['attempt_id'])->not->toBeNull()
        ->and($payload['fresh_grades'][0]['course_title'])->toBe('Geometrie')
        ->and($payload['fresh_grades'][0]['score'])->toBe(7)
        ->and($payload['priority'])->toBe(1)
        ->and($payload['open_jumps'][0]['id'])->toBe($current->id);
});

test('delayed sessions stay hidden until expiry and immediate sessions show their score', function () {
    $paper = Paper::factory()->create(['title' => 'Sujet']);
    $delayed = KangourouSession::factory()->create(['paper_id' => $paper->id]);
    $delayed->divisions()->attach($this->division->id);
    Attempt::factory()->finished()->create([
        'kangourou_session_id' => $delayed->id,
        'user_id' => $this->student->id,
        'score' => 15,
    ]);

    $immediate = KangourouSession::factory()->create([
        'paper_id' => $paper->id,
        'preferences' => array_replace(KangourouSession::DEFAULT_PREFERENCES, ['correction' => 'immediate']),
    ]);
    $immediate->divisions()->attach($this->division->id);
    $immediateAttempt = Attempt::factory()->finished()->create([
        'kangourou_session_id' => $immediate->id,
        'user_id' => $this->student->id,
        'score' => 21,
        'updated_at' => now()->subHours(2),
    ]);

    $before = collect($this->actingAs($this->student)->getJson('/api/my/pocket')->json('classes.0.recent_sessions'));

    expect($before->pluck('id')->all())->toBe([$immediate->id])
        ->and($before->first()['score'])->toBe(21)
        ->and($before->first()['attempt_id'])->toBe($immediateAttempt->id);

    $delayed->update(['status' => 'expired', 'expires_at' => now()->subHour()]);

    $after = collect($this->actingAs($this->student)->getJson('/api/my/pocket')->json('classes.0.recent_sessions'));

    expect($after->pluck('id')->all())->toBe([$delayed->id, $immediate->id])
        ->and($after->first()['revealed_at'])->toBe($delayed->fresh()->expires_at->toIso8601String());
});

test('sessions older than 42 days are omitted and only three are returned', function () {
    $paper = Paper::factory()->create();
    foreach ([1, 2, 3, 4] as $daysAgo) {
        $session = KangourouSession::factory()->expired()->create([
            'paper_id' => $paper->id,
            'expires_at' => now()->subDays($daysAgo),
        ]);
        $session->divisions()->attach($this->division->id);
        Attempt::factory()->finished()->create([
            'kangourou_session_id' => $session->id,
            'user_id' => $this->student->id,
            'score' => $daysAgo,
        ]);
    }
    $old = KangourouSession::factory()->expired()->create([
        'paper_id' => $paper->id,
        'expires_at' => now()->subDays(43),
    ]);
    $old->divisions()->attach($this->division->id);
    Attempt::factory()->finished()->create([
        'kangourou_session_id' => $old->id,
        'user_id' => $this->student->id,
        'score' => 1,
    ]);

    $recent = $this->actingAs($this->student)->getJson('/api/my/pocket')->json('classes.0.recent_sessions');

    expect($recent)->toHaveCount(3)
        ->and(collect($recent)->pluck('id'))->not->toContain($old->id);
});

test('public questions omit the answer and private ones are absent', function () {
    $public = SuggestedQuestion::factory()->public()->create([
        'course_id' => $this->course->id,
        'level' => 2,
    ]);
    SuggestedQuestion::factory()->create(['course_id' => $this->course->id]);

    $questions = $this->actingAs($this->student)->getJson('/api/my/pocket')->json('classes.0.open_questions');

    expect($questions)->toHaveCount(1)
        ->and($questions[0]['id'])->toBe($public->id)
        ->and($questions[0]['question'])->not->toHaveKey('correct_answer')
        ->and($questions[0]['course_id'])->toBe($this->course->id)
        ->and($questions[0]['question']['image'])->toBe($public->question->image)
        ->and($this->actingAs($this->student)->getJson('/api/my/pocket')->json('classes.0.priority'))->toBe(4);
});

test('another student score is not in the payload', function () {
    $other = User::factory()->create();
    $this->division->students()->attach($other->id);
    $jump = Jump::factory()->expired()->create(['course_id' => $this->course->id]);
    $attempt = JumpAttempt::create([
        'jump_id' => $jump->id,
        'user_id' => $other->id,
        'status' => 'finished',
        'score' => 88,
    ]);

    $encoded = json_encode($this->actingAs($this->student)->getJson('/api/my/pocket')->json());

    expect($encoded)->not->toContain('"attempt_id":'.$attempt->id)
        ->and($encoded)->not->toContain('"score":88');
});

test('pending invites are returned for the signed in email', function () {
    $invite = DivisionInvite::factory()->create([
        'email' => $this->student->email,
        'status' => 'pending',
    ]);
    DivisionInvite::factory()->create([
        'email' => $this->student->email,
        'status' => 'declined',
    ]);

    $invites = $this->actingAs($this->student)->getJson('/api/my/pocket')->json('invites');

    expect($invites)->toHaveCount(1)
        ->and($invites[0]['id'])->toBe($invite->id)
        ->and($invites[0]['division']['id'])->toBe($invite->division_id);
});

test('a teacher who is also a student gets the teacher pocket only', function () {
    Role::findOrCreate('Teacher', 'web');
    $this->teacher->assignRole('Teacher');
    $learned = Division::factory()->create(['name' => 'Learned']);
    $learned->students()->attach($this->teacher->id);
    $learnedCourse = Course::factory()->create(['division_id' => $learned->id]);
    $jump = Jump::factory()->expired()->create(['course_id' => $learnedCourse->id]);
    JumpAttempt::create([
        'jump_id' => $jump->id,
        'user_id' => $this->teacher->id,
        'status' => 'finished',
        'score' => 5,
    ]);
    Jump::factory()->active()->create(['course_id' => $this->course->id]);
    SuggestedQuestion::factory()->create(['course_id' => $this->course->id]);
    $public = SuggestedQuestion::factory()->public()->create([
        'course_id' => $this->course->id,
        'level' => 2,
    ]);
    DivisionInvite::factory()->create(['email' => $this->teacher->email, 'status' => 'pending']);

    $otherTeacher = User::factory()->create();
    $foreign = Division::factory()->create(['teacher_id' => $otherTeacher->id, 'name' => 'Foreign']);

    $response = $this->actingAs($this->teacher)->getJson('/api/my/pocket');
    $class = $response->json('classes.0');

    expect($response->json('viewer'))->toBe('teacher')
        ->and($response->json('invites'))->toHaveCount(1)
        ->and(collect($response->json('classes'))->pluck('id')->all())->toBe([$this->division->id])
        ->and(collect($response->json('classes'))->pluck('id'))->not->toContain($foreign->id)
        ->and($class['open_jumps'])->toHaveCount(1)
        ->and($class['open_jumps'][0])->not->toHaveKey('action')
        ->and($class['open_questions'])->toHaveCount(1)
        ->and($class['open_questions'][0]['id'])->toBe($public->id)
        ->and($class['open_questions'][0]['level'])->toBe(2)
        ->and($class['open_questions'][0]['course_id'])->toBe($this->course->id)
        ->and($class['open_questions'][0]['question'])->not->toHaveKey('correct_answer')
        ->and($class['open_questions'][0])->not->toHaveKey('unpublished_count')
        ->and(json_encode($class))->not->toContain('attempt_id')
        ->and(json_encode($class))->not->toContain('"score"');
});

test('teacher recent sessions ignore drafts and keep ended sessions', function () {
    Role::findOrCreate('Teacher', 'web');
    $this->teacher->assignRole('Teacher');
    $paper = Paper::factory()->create(['title' => 'Expiree']);
    $ended = KangourouSession::factory()->expired()->create([
        'paper_id' => $paper->id,
        'author_id' => $this->teacher->id,
        'expires_at' => now()->subHours(3),
    ]);
    $ended->divisions()->attach($this->division->id);
    $draft = KangourouSession::factory()->draft()->create([
        'paper_id' => $paper->id,
        'author_id' => $this->teacher->id,
        'expires_at' => now()->subDay(),
    ]);
    $draft->divisions()->attach($this->division->id);
    $closing = KangourouSession::factory()->create([
        'paper_id' => $paper->id,
        'author_id' => User::factory()->create()->id,
        'status' => 'active',
        'expires_at' => now()->subMinutes(10),
    ]);
    $closing->divisions()->attach($this->division->id);

    $recent = collect($this->actingAs($this->teacher)->getJson('/api/my/pocket')->json('classes.0.recent_sessions'));

    expect($recent->pluck('id')->all())->toBe([$closing->id, $ended->id])
        ->and($recent->firstWhere('id', $ended->id)['is_author'])->toBeTrue()
        ->and($recent->firstWhere('id', $closing->id)['is_author'])->toBeFalse()
        ->and($recent->firstWhere('id', $ended->id)['fresh'])->toBeTrue();
});

test('expiring the same class twice notifies the division once per phase', function () {
    Event::fake([JumpExpired::class, PocketChanged::class]);
    Jump::factory()->create([
        'course_id' => $this->course->id,
        'status' => 'active',
        'expiration' => now()->subMinute(),
    ]);
    Jump::factory()->create([
        'course_id' => $this->course->id,
        'status' => 'active',
        'expiration' => now()->subMinute(),
    ]);

    (new ExpireJumps)->handle();

    Event::assertDispatchedTimes(JumpExpired::class, 2);
    Event::assertDispatchedTimes(PocketChanged::class, 2);
    Event::assertDispatched(PocketChanged::class, function (PocketChanged $event): bool {
        return $event->divisionId === $this->division->id
            && $event->broadcastWith() === ['division_id' => $this->division->id]
            && $event->broadcastOn()[0]->name === 'private-division.'.$this->division->id;
    });
});

test('activating a jump does not emit PocketChanged', function () {
    Event::fake([JumpActivated::class, PocketChanged::class]);
    $jump = Jump::factory()->create(['course_id' => $this->course->id, 'status' => 'draft']);

    $this->actingAs($this->teacher)->patchJson("/api/jumps/{$jump->id}", ['status' => 'active'])->assertOk();

    Event::assertDispatched(JumpActivated::class);
    Event::assertNotDispatched(PocketChanged::class);
});

test('closing a jump emits PocketChanged beside JumpExpired', function () {
    Event::fake([JumpExpired::class, PocketChanged::class]);
    $jump = Jump::factory()->active()->create(['course_id' => $this->course->id]);

    $this->actingAs($this->teacher)->patchJson("/api/jumps/{$jump->id}", [
        'status' => 'expiring',
    ])->assertOk();

    Event::assertDispatched(JumpExpired::class);
    Event::assertDispatched(PocketChanged::class, fn (PocketChanged $event) => $event->divisionId === $this->division->id);
});

test('moving a session expiry later does not emit PocketChanged', function () {
    Event::fake([SessionExpired::class, PocketChanged::class]);
    $session = KangourouSession::factory()->withAuthor($this->teacher)->create();
    $session->divisions()->attach($this->division->id);

    $this->actingAs($this->teacher)->patchJson("/api/kangourou-sessions/{$session->id}", [
        'expires_at' => now()->addHours(3)->toIso8601String(),
    ])->assertOk();

    Event::assertNotDispatched(SessionExpired::class);
    Event::assertNotDispatched(PocketChanged::class);
});

test('moving a session expiry into the past emits PocketChanged', function () {
    Event::fake([SessionExpired::class, PocketChanged::class]);
    $session = KangourouSession::factory()->withAuthor($this->teacher)->create();
    $session->divisions()->attach($this->division->id);

    $this->actingAs($this->teacher)->patchJson("/api/kangourou-sessions/{$session->id}", [
        'expires_at' => now()->subMinute()->toIso8601String(),
    ])->assertOk();

    Event::assertDispatched(SessionExpired::class);
    Event::assertDispatched(PocketChanged::class, fn (PocketChanged $event) => $event->divisionId === $this->division->id);
});

test('an immediate submit notifies only that user', function () {
    Event::fake([PocketChanged::class]);
    $session = KangourouSession::factory()->create([
        'preferences' => array_replace(KangourouSession::DEFAULT_PREFERENCES, ['correction' => 'immediate']),
    ]);
    $session->divisions()->attach($this->division->id);
    $attempt = Attempt::factory()->withUser($this->student)->create([
        'kangourou_session_id' => $session->id,
    ]);

    $this->actingAs($this->student)->postJson("/api/attempts/{$attempt->id}/submit")->assertOk();

    Event::assertDispatched(PocketChanged::class, function (PocketChanged $event): bool {
        return $event->userId === $this->student->id
            && $event->divisionId === null
            && $event->broadcastOn()[0]->name === 'private-App.Models.User.'.$this->student->id;
    });
    Event::assertNotDispatched(PocketChanged::class, fn (PocketChanged $event) => $event->divisionId !== null);
});

test('a delayed submit does not emit PocketChanged', function () {
    Event::fake([PocketChanged::class]);
    $session = KangourouSession::factory()->create();
    $attempt = Attempt::factory()->withUser($this->student)->create([
        'kangourou_session_id' => $session->id,
    ]);

    $this->actingAs($this->student)->postJson("/api/attempts/{$attempt->id}/submit")->assertOk();

    Event::assertNotDispatched(PocketChanged::class);
});
