<?php

namespace App\Jobs;

use App\Models\Attempt;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class UpdateMasteryAndDifficulty implements ShouldQueue
{
    use Queueable;

    public function __construct(public Attempt $attempt) {}

    public function handle(): void
    {
        $attempt = $this->attempt->load('kangourouSession.paper.questions', 'user');
        $userId = $attempt->user_id;

        if (! $userId) {
            return;
        }

        $session = $attempt->kangourouSession;
        $questions = $session->paper->questions()->orderByPivot('order')->get();
        $answers = $attempt->answers;

        DB::transaction(function () use ($userId, $questions, $answers): void {
            $user = User::lockForUpdate()->find($userId);

            if (! $user) {
                return;
            }

            $userMastery = $user->mastery ?? 0;

            foreach ($questions as $index => $question) {
                $userAnswer = $answers[$index]['answer'] ?? null;

                if ($userAnswer === null) {
                    continue;
                }

                $isCorrect = strtoupper($userAnswer) === strtoupper($question->correct_answer);
                $questionDifficulty = $question->difficulty ?? 0;
                $difference = $userMastery - $questionDifficulty;

                if ($difference < 0 && $isCorrect) {
                    $userMastery += (int) ceil(-$difference * 0.1);
                    $question->difficulty = $questionDifficulty - (int) ceil(-$difference * 0.01);
                    $question->save();
                } elseif ($difference > 0 && ! $isCorrect) {
                    $userMastery -= (int) ceil($difference * 0.1);
                    $question->difficulty = $questionDifficulty + (int) ceil($difference * 0.01);
                    $question->save();
                }
            }

            $user->mastery = $userMastery;
            $user->save();
        });
    }
}
