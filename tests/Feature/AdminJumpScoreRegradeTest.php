<?php

use App\Events\PocketChanged;
use App\Jobs\AnalyseJump;
use App\Jobs\RegradeJumpScores;
use App\Models\Course;
use App\Models\Division;
use App\Models\Jump;
use App\Models\JumpAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Cache::flush();
    Role::create(['name' => 'Admin']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->teacher = User::factory()->create();
    $this->division = Division::factory()->create(['teacher_id' => $this->teacher->id]);
    $this->student = User::factory()->create(['mastery' => 800]);
    $this->division->students()->attach($this->student->id);
    $this->course = Course::factory()->create(['division_id' => $this->division->id]);
    $this->question = Question::factory()->create(['correct_answer' => 'A', 'difficulty' => 1000]);
});

test('guests and non-admins cannot regrade jump scores', function () {
    $this->getJson('/api/admin/jump-scores')->assertUnauthorized();
    $this->postJson('/api/admin/jump-scores/regrade')->assertUnauthorized();

    $this->actingAs($this->teacher)->getJson('/api/admin/jump-scores')->assertForbidden();
    $this->actingAs($this->teacher)->postJson('/api/admin/jump-scores/regrade')->assertForbidden();
});

test('admin regrade rewrites expired graded attempts and leaves the rest alone', function () {
    Event::fake([PocketChanged::class]);
    Bus::fake([AnalyseJump::class]);

    $expired = Jump::factory()->expired()->create(['course_id' => $this->course->id, 'nb_questions' => 1]);
    $graded = JumpAttempt::create([
        'jump_id' => $expired->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => $this->question->id, 'answer' => 'A', 'status' => 'correct', 'difficulty' => 1000],
        ],
        'score' => 1000,
        'status' => 'finished',
        'termination' => 'submitted',
    ]);

    $otherStudent = User::factory()->create(['mastery' => 800]);
    $this->division->students()->attach($otherStudent->id);
    $pending = JumpAttempt::create([
        'jump_id' => $expired->id,
        'user_id' => $otherStudent->id,
        'question_list' => [
            ['id' => $this->question->id, 'answer' => 'A', 'status' => 'pending', 'difficulty' => 1000],
        ],
        'score' => 0,
        'status' => 'finished',
        'termination' => 'submitted',
    ]);

    $activeJump = Jump::factory()->active()->create(['course_id' => $this->course->id, 'nb_questions' => 1]);
    $activeAttempt = JumpAttempt::create([
        'jump_id' => $activeJump->id,
        'user_id' => $this->student->id,
        'question_list' => [
            ['id' => $this->question->id, 'answer' => 'B', 'status' => 'incorrect', 'difficulty' => 1000],
        ],
        'score' => 1000,
        'status' => 'finished',
        'termination' => 'submitted',
    ]);

    $wrongJump = Jump::factory()->expired()->create(['course_id' => $this->course->id, 'nb_questions' => 3]);
    $thirdStudent = User::factory()->create(['mastery' => 800]);
    $this->division->students()->attach($thirdStudent->id);
    $wrongAttempt = JumpAttempt::create([
        'jump_id' => $wrongJump->id,
        'user_id' => $thirdStudent->id,
        'question_list' => [
            ['id' => $this->question->id, 'answer' => 'B', 'status' => 'incorrect', 'difficulty' => 1000],
            ['id' => $this->question->id, 'answer' => 'C', 'status' => 'incorrect', 'difficulty' => 1000],
            ['id' => $this->question->id, 'answer' => 'D', 'status' => 'incorrect', 'difficulty' => 1000],
        ],
        'score' => 3000,
        'status' => 'finished',
        'termination' => 'submitted',
    ]);

    $preview = $this->actingAs($this->admin)->getJson('/api/admin/jump-scores');
    $preview->assertOk();
    expect($preview->json('jumps'))->toBe(2)
        ->and($preview->json('attempts'))->toBe(2);

    $this->actingAs($this->admin)->postJson('/api/admin/jump-scores/regrade')
        ->assertStatus(202)
        ->assertJson(['state' => 'running']);

    expect($graded->fresh()->score)->toBe(10)
        ->and($graded->fresh()->question_list[0]['points'])->toBe(10)
        ->and($graded->fresh()->question_list[0]['status'])->toBe('correct')
        ->and($pending->fresh()->score)->toBe(0)
        ->and($pending->fresh()->question_list[0]['status'])->toBe('pending')
        ->and($pending->fresh()->question_list[0])->not->toHaveKey('points')
        ->and($activeAttempt->fresh()->score)->toBe(1000)
        ->and($activeAttempt->fresh()->question_list[0])->not->toHaveKey('points')
        ->and($wrongAttempt->fresh()->question_list[0]['points'])->toBe(-1)
        ->and($wrongAttempt->fresh()->score)->toBe(0)
        ->and($this->student->fresh()->mastery)->toBe(800)
        ->and($this->question->fresh()->difficulty)->toBe(1000);

    Bus::assertNotDispatched(AnalyseJump::class);
    Event::assertDispatched(PocketChanged::class);

    $this->actingAs($this->admin)->getJson('/api/admin/jump-scores/regrade')
        ->assertOk()
        ->assertJson([
            'state' => 'done',
            'jumps' => 2,
            'attempts' => 2,
        ]);

    $this->actingAs($this->admin)->postJson('/api/admin/jump-scores/regrade')->assertStatus(202);

    expect($graded->fresh()->score)->toBe(10)
        ->and($wrongAttempt->fresh()->score)->toBe(0)
        ->and($this->student->fresh()->mastery)->toBe(800);
});

test('a second regrade is rejected while one is running', function () {
    $lock = Cache::lock(RegradeJumpScores::LOCK, 60);
    expect($lock->get())->toBeTrue();

    $this->actingAs($this->admin)->postJson('/api/admin/jump-scores/regrade')
        ->assertStatus(409)
        ->assertJson(['message' => 'Un recalcul est déjà en cours.']);

    $lock->release();
});
