<?php

use App\Models\Attempt;
use App\Models\KangourouSession;
use App\Models\Paper;
use App\Services\GradingService;

beforeEach(function () {
    $this->paper = Paper::factory()->withQuestions()->create();
    $this->session = KangourouSession::factory()->create(['paper_id' => $this->paper->id]);
    $this->gradingService = new GradingService;
});

test('all correct answers gives maximum score with tier 4 bonus', function () {
    $questions = $this->paper->questions()->orderByPivot('order')->get();
    $answers = [];
    foreach ($questions as $question) {
        $answers[] = ['answer' => $question->correct_answer, 'status' => 'answered'];
    }

    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'answers' => $answers,
    ]);

    $score = $this->gradingService->grade($attempt);

    // Tier 1: 8 * 3 = 24, Tier 2: 8 * 4 = 32, Tier 3: 8 * 5 = 40, Tier 4: 2 * 1 = 2
    expect($score)->toBe(98);
});

test('all unanswered gives score of 0', function () {
    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'answers' => Attempt::defaultAnswers(),
    ]);

    $score = $this->gradingService->grade($attempt);

    expect($score)->toBe(0);
});

test('all wrong answers gives minimum score of 0', function () {
    $questions = $this->paper->questions()->orderByPivot('order')->get();
    $answers = [];
    foreach ($questions as $question) {
        // Pick a wrong answer
        $wrongAnswer = $question->correct_answer === 'A' ? 'B' : 'A';
        $answers[] = ['answer' => $wrongAnswer, 'status' => 'answered'];
    }

    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'answers' => $answers,
    ]);

    $score = $this->gradingService->grade($attempt);

    // Penalties would be -24 but minimum score is 0
    expect($score)->toBe(0);
});

test('tier 4 bonus only awarded when all Q1-24 are correct', function () {
    $questions = $this->paper->questions()->orderByPivot('order')->get();
    $answers = [];
    foreach ($questions as $index => $question) {
        if ($index < 24) {
            $answers[] = ['answer' => $question->correct_answer, 'status' => 'answered'];
        } else {
            // Wrong for Q25-26 (but this doesn't matter, tier 4 is bonus for Q1-24 all correct)
            $wrongAnswer = $question->correct_answer === 'A' ? 'B' : 'A';
            $answers[] = ['answer' => $wrongAnswer, 'status' => 'answered'];
        }
    }

    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'answers' => $answers,
    ]);

    $score = $this->gradingService->grade($attempt);

    // Tier 1: 24, Tier 2: 32, Tier 3: 40, Tier 4: 2 (all Q1-24 correct)
    expect($score)->toBe(98);
});

test('tier 4 bonus not awarded when any Q1-24 is wrong', function () {
    $questions = $this->paper->questions()->orderByPivot('order')->get();
    $answers = [];
    foreach ($questions as $index => $question) {
        if ($index === 0) {
            // Q1 wrong
            $wrongAnswer = $question->correct_answer === 'A' ? 'B' : 'A';
            $answers[] = ['answer' => $wrongAnswer, 'status' => 'answered'];
        } else {
            $answers[] = ['answer' => $question->correct_answer, 'status' => 'answered'];
        }
    }

    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'answers' => $answers,
    ]);

    $score = $this->gradingService->grade($attempt);

    // Tier 1: 7*3 - 0.75 = 20.25, Tier 2: 32, Tier 3: 40, Tier 4: 0 (Q1 wrong)
    expect($score)->toBe(92);
});

test('gradeAndSave marks answers and saves score', function () {
    $questions = $this->paper->questions()->orderByPivot('order')->get();
    $answers = [];
    foreach ($questions as $index => $question) {
        if ($index < 5) {
            $answers[] = ['answer' => $question->correct_answer, 'status' => 'answered'];
        } else {
            $answers[] = ['answer' => null, 'status' => 'unanswered'];
        }
    }

    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $this->session->id,
        'answers' => $answers,
    ]);

    $score = $this->gradingService->gradeAndSave($attempt);

    $attempt->refresh();
    expect($attempt->status)->toBe('finished');
    expect($attempt->score)->toBe($score);
    expect($attempt->answers[0]['status'])->toBe('correct');
    expect($attempt->answers[10]['status'])->toBe('unanswered');
});

test('tier 4 bonus always awarded when only_count_tier4_if_all_before_correct is false', function () {
    $session = KangourouSession::factory()->create([
        'paper_id' => $this->paper->id,
        'preferences' => ['only_count_tier4_if_all_before_correct' => false],
    ]);

    $questions = $this->paper->questions()->orderByPivot('order')->get();
    $answers = [];
    foreach ($questions as $index => $question) {
        if ($index === 0) {
            // Q1 is wrong — tier 4 bonus should still apply
            $wrongAnswer = $question->correct_answer === 'A' ? 'B' : 'A';
            $answers[] = ['answer' => $wrongAnswer, 'status' => 'answered'];
        } else {
            $answers[] = ['answer' => $question->correct_answer, 'status' => 'answered'];
        }
    }

    $attempt = Attempt::factory()->create([
        'kangourou_session_id' => $session->id,
        'answers' => $answers,
    ]);

    $score = $this->gradingService->grade($attempt);

    // Q1 wrong: 7*3 - 0.75 = 20.25 → 20 (floor), tier2: 32, tier3: 40, tier4 bonus: 2 (always awarded)
    // Total: 7*3 + 8*4 + 8*5 + 2*1 - 0.25*3 = 21 + 32 + 40 + 2 - 0.75 = 94.25 → 94
    expect($score)->toBe(94);
});

test('default preferences include all new fields', function () {
    $defaults = KangourouSession::DEFAULT_PREFERENCES;

    expect($defaults)->toHaveKey('time_limit');
    expect($defaults)->toHaveKey('blur_security');
    expect($defaults)->toHaveKey('only_count_tier4_if_all_before_correct');
    expect($defaults)->toHaveKey('shuffle');
    expect($defaults['time_limit'])->toBe(50);
    expect($defaults['blur_security'])->toBeTrue();
    expect($defaults['only_count_tier4_if_all_before_correct'])->toBeTrue();
    expect($defaults['shuffle'])->toBe('none');
});
