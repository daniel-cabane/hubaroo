<?php

namespace App\Jobs;

use App\Models\Attempt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdateMasteryAndDifficulty implements ShouldQueue
{
    use Queueable;

    public function __construct(public Attempt $attempt) {}

    public function handle(): void
    {
        $attempt = $this->attempt->load('kangourouSession.paper.questions', 'user');
        $user = $attempt->user;

        if (! $user) {
            return;
        }

        $session = $attempt->kangourouSession;
        $questions = $session->paper->questions()->orderByPivot('order')->get();
        $answers = $attempt->answers;

        foreach ($questions as $index => $question) {
            $userAnswer = $answers[$index]['answer'] ?? null;

            if ($userAnswer === null) {
                continue;
            }

            $isCorrect = strtoupper($userAnswer) === strtoupper($question->correct_answer);
            $userMastery = $user->mastery ?? 0;
            $questionDifficulty = $question->difficulty ?? 0;
            $difference = $userMastery - $questionDifficulty;

            if ($difference < 0 && $isCorrect) {
                $user->mastery = $userMastery + (int) ceil(-$difference * 0.1);
                $question->difficulty = $questionDifficulty - (int) ceil(-$difference * 0.01);
                $question->save();
            } elseif ($difference > 0 && ! $isCorrect) {
                $user->mastery = $userMastery - (int) ceil($difference * 0.1);
                $question->difficulty = $questionDifficulty + (int) ceil($difference * 0.01);
                $question->save();
            }
        }

        $user->save();
    }
}
