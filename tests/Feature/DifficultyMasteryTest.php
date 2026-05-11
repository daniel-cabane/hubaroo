<?php

use App\Jobs\UpdateMasteryAndDifficulty;
use App\Models\Attempt;
use App\Models\KangourouSession;
use App\Models\Paper;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::create(['name' => 'Teacher']);
    Role::create(['name' => 'Student']);
});

// --- Registration mastery tests ---

test('student registration sets mastery based on birth year', function () {
    $response = $this->postJson('/register', [
        'name' => 'Test Student',
        'email' => 'student@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'Student',
        'birth_year' => 2010,
    ]);

    $response->assertCreated();

    $user = User::where('email', 'student@example.com')->firstOrFail();
    $expectedAge = (int) date('Y') - 2010;
    $expectedMastery = min($expectedAge - 8, 1) * 150;

    expect($user->mastery)->toBe($expectedMastery);
});

test('teacher registration does not set mastery', function () {
    $response = $this->postJson('/register', [
        'name' => 'Test Teacher',
        'email' => 'teacher@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'Teacher',
    ]);

    $response->assertCreated();

    $user = User::where('email', 'teacher@example.com')->firstOrFail();
    expect($user->mastery)->toBeNull();
});

test('student registration requires birth year', function () {
    $response = $this->postJson('/register', [
        'name' => 'Test Student',
        'email' => 'student2@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'Student',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['birth_year']);
});

// --- Question difficulty formula tests ---

test('question difficulty is calculated correctly at tier 1', function () {
    $question = Question::factory()->create(['tier' => 1, 'difficulty' => 300 * 2 + 100 * 1]);

    expect($question->difficulty)->toBe(700);
});

test('question difficulty is calculated correctly at tier 4', function () {
    $question = Question::factory()->create(['tier' => 4, 'difficulty' => 300 * 5 + 100 * 8]);

    expect($question->difficulty)->toBe(2300);
});

// --- UpdateMasteryAndDifficulty job tests ---

test('correct answer to harder question increases user mastery and decreases question difficulty', function () {
    $user = User::factory()->create(['mastery' => 500]);
    $paper = Paper::factory()->create(['level' => 's']);
    $session = KangourouSession::factory()->create(['paper_id' => $paper->id]);

    $questions = collect();
    for ($i = 1; $i <= 26; $i++) {
        $tier = $i <= 8 ? 1 : ($i <= 16 ? 2 : ($i <= 24 ? 3 : 4));
        $question = Question::factory()->create(['tier' => $tier, 'difficulty' => 1800, 'correct_answer' => 'A']);
        $paper->questions()->attach($question->id, ['order' => $i]);
        $questions->push($question);
    }

    $answers = $questions->map(fn ($q) => ['answer' => 'A', 'status' => 'correct'])->toArray();

    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $session->id,
        'user_id' => $user->id,
        'answers' => $answers,
        'status' => 'finished',
    ]);

    (new UpdateMasteryAndDifficulty($attempt))->handle();

    $user->refresh();
    $firstQuestion = $questions->first()->fresh();

    expect($user->mastery)->toBeGreaterThan(500);
    expect($firstQuestion->difficulty)->toBeLessThan(1800);
});

test('incorrect answer to easier question decreases user mastery and increases question difficulty', function () {
    $user = User::factory()->create(['mastery' => 1500]);
    $paper = Paper::factory()->create(['level' => 'e']);
    $session = KangourouSession::factory()->create(['paper_id' => $paper->id]);

    $questions = collect();
    for ($i = 1; $i <= 26; $i++) {
        $tier = $i <= 8 ? 1 : ($i <= 16 ? 2 : ($i <= 24 ? 3 : 4));
        $question = Question::factory()->create(['tier' => $tier, 'difficulty' => 400, 'correct_answer' => 'A']);
        $paper->questions()->attach($question->id, ['order' => $i]);
        $questions->push($question);
    }

    $answers = $questions->map(fn ($q) => ['answer' => 'B', 'status' => 'incorrect'])->toArray();

    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $session->id,
        'user_id' => $user->id,
        'answers' => $answers,
        'status' => 'finished',
    ]);

    (new UpdateMasteryAndDifficulty($attempt))->handle();

    $user->refresh();
    $firstQuestion = $questions->first()->fresh();

    expect($user->mastery)->toBeLessThan(1500);
    expect($firstQuestion->difficulty)->toBeGreaterThan(400);
});

test('correct answer to easier question does not change mastery or difficulty', function () {
    $user = User::factory()->create(['mastery' => 1500]);
    $paper = Paper::factory()->create(['level' => 'e']);
    $session = KangourouSession::factory()->create(['paper_id' => $paper->id]);

    $questions = collect();
    for ($i = 1; $i <= 26; $i++) {
        $tier = $i <= 8 ? 1 : ($i <= 16 ? 2 : ($i <= 24 ? 3 : 4));
        $question = Question::factory()->create(['tier' => $tier, 'difficulty' => 400, 'correct_answer' => 'A']);
        $paper->questions()->attach($question->id, ['order' => $i]);
        $questions->push($question);
    }

    $answers = $questions->map(fn ($q) => ['answer' => 'A', 'status' => 'correct'])->toArray();

    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $session->id,
        'user_id' => $user->id,
        'answers' => $answers,
        'status' => 'finished',
    ]);

    (new UpdateMasteryAndDifficulty($attempt))->handle();

    $user->refresh();
    $firstQuestion = $questions->first()->fresh();

    expect($user->mastery)->toBe(1500);
    expect($firstQuestion->difficulty)->toBe(400);
});

test('guest attempt skips mastery and difficulty update', function () {
    $paper = Paper::factory()->create(['level' => 'e']);
    $session = KangourouSession::factory()->create(['paper_id' => $paper->id]);

    $questions = collect();
    for ($i = 1; $i <= 26; $i++) {
        $tier = $i <= 8 ? 1 : ($i <= 16 ? 2 : ($i <= 24 ? 3 : 4));
        $question = Question::factory()->create(['tier' => $tier, 'difficulty' => 400, 'correct_answer' => 'A']);
        $paper->questions()->attach($question->id, ['order' => $i]);
        $questions->push($question);
    }

    $answers = $questions->map(fn ($q) => ['answer' => 'A', 'status' => 'correct'])->toArray();

    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $session->id,
        'user_id' => null,
        'answers' => $answers,
        'status' => 'finished',
    ]);

    (new UpdateMasteryAndDifficulty($attempt))->handle();

    $firstQuestion = $questions->first()->fresh();
    expect($firstQuestion->difficulty)->toBe(400);
});

test('submitting an attempt dispatches UpdateMasteryAndDifficulty job', function () {
    Queue::fake();

    $paper = Paper::factory()->withQuestions()->create();
    $session = KangourouSession::factory()->create(['paper_id' => $paper->id]);
    $attempt = Attempt::factory()->create(['kangourou_session_id' => $session->id]);

    $this->postJson("/api/attempts/{$attempt->id}/submit");

    Queue::assertPushed(UpdateMasteryAndDifficulty::class, fn ($job) => $job->attempt->id === $attempt->id);
});
