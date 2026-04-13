<?php

namespace App\Services;

use App\Models\Attempt;

class GradingService
{
    /**
     * Grade an attempt and return the score.
     *
     * Scoring rules (from session preferences):
     * - Tier 1 (Q1-8): +tier1 for correct, -(tier1 * penalty_fraction) for incorrect
     * - Tier 2 (Q9-16): +tier2 for correct, -(tier2 * penalty_fraction) for incorrect
     * - Tier 3 (Q17-24): +tier3 for correct, -(tier3 * penalty_fraction) for incorrect
     * - Tier 4 (Q25-26): +tier4_bonus only if ALL Q1-24 answers are correct
     */
    public function grade(Attempt $attempt): int
    {
        $session = $attempt->kangourouSession;
        $preferences = $session->getEffectivePreferences();
        $grading = $preferences['grading'];

        $questions = $session->paper->questions()->orderByPivot('order')->get();
        $answers = $attempt->answers;

        $score = 0.0;
        $allFirstTwentyFourCorrect = true;

        foreach ($questions as $index => $question) {
            $userAnswer = $answers[$index]['answer'] ?? null;
            $isCorrect = $userAnswer !== null && strtoupper($userAnswer) === strtoupper($question->correct_answer);
            $isAnswered = $userAnswer !== null;

            $order = $index + 1;

            if ($order <= 24) {
                if ($isCorrect) {
                    if ($order <= 8) {
                        $score += $grading['tier1'];
                    } elseif ($order <= 16) {
                        $score += $grading['tier2'];
                    } else {
                        $score += $grading['tier3'];
                    }
                } elseif ($isAnswered) {
                    if ($order <= 8) {
                        $score -= $grading['tier1'] * $grading['penalty_fraction'];
                    } elseif ($order <= 16) {
                        $score -= $grading['tier2'] * $grading['penalty_fraction'];
                    } else {
                        $score -= $grading['tier3'] * $grading['penalty_fraction'];
                    }
                }

                if (! $isCorrect) {
                    $allFirstTwentyFourCorrect = false;
                }
            }
        }

        // Tier 4 bonus: award points for Q25-26 only if all Q1-24 are correct
        if ($allFirstTwentyFourCorrect) {
            $tier4Count = $questions->count() - 24;
            $score += $grading['tier4_bonus'] * $tier4Count;
        }

        return (int) round(max(0, $score));
    }

    /**
     * Grade an attempt, save the score and mark answers as correct/incorrect.
     */
    public function gradeAndSave(Attempt $attempt): int
    {
        $session = $attempt->kangourouSession;
        $questions = $session->paper->questions()->orderByPivot('order')->get();
        $answers = $attempt->answers;

        foreach ($questions as $index => $question) {
            $userAnswer = $answers[$index]['answer'] ?? null;

            if ($userAnswer === null) {
                $answers[$index]['status'] = 'unanswered';
            } elseif (strtoupper($userAnswer) === strtoupper($question->correct_answer)) {
                $answers[$index]['status'] = 'correct';
            } else {
                $answers[$index]['status'] = 'incorrect';
            }
        }

        $attempt->answers = $answers;
        $score = $this->grade($attempt);
        $attempt->score = $score;
        $attempt->status = 'finished';
        $attempt->save();

        return $score;
    }
}
