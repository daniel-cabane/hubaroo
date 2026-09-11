<?php

namespace App\Services;

use App\Models\JumpAttempt;
use App\Models\Question;
use Illuminate\Support\Collection;

class JumpGradingService
{
    /**
     * @param  Collection<int, string>|null  $correctAnswers
     */
    public function gradeAttempt(JumpAttempt $attempt, ?Collection $correctAnswers = null): void
    {
        $questionList = $attempt->question_list ?? [];

        if ($correctAnswers === null) {
            $questionIds = collect($questionList)->pluck('id')->unique()->filter();
            $correctAnswers = Question::whereIn('id', $questionIds)->pluck('correct_answer', 'id');
        }

        $score = 0;

        foreach ($questionList as &$item) {
            $correct = $correctAnswers->get($item['id']);
            $given = $item['answer'] ?? null;

            if ($given !== null && $correct !== null) {
                $item['status'] = $given === $correct ? 'correct' : 'incorrect';
            } else {
                $item['status'] = 'incorrect';
            }

            if ($item['status'] === 'correct') {
                $score += (int) ($item['difficulty'] ?? 0);
            }
        }
        unset($item);

        $attempt->update(['question_list' => $questionList, 'score' => $score]);
    }
}
