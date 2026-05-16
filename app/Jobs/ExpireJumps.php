<?php

namespace App\Jobs;

use App\Events\JumpExpired;
use App\Models\Jump;
use App\Models\JumpAttempt;
use App\Models\Question;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class ExpireJumps
{
    use Queueable;

    public function handle(): void
    {
        // Transition active jumps that have reached their expiration to 'expiring'
        Jump::where('status', 'active')
            ->where('expiration', '<=', now())
            ->update(['status' => 'expiring']);

        // Grade all expiring jumps (auto-expired above + manually set to 'expiring' by teacher)
        $jumps = Jump::where('status', 'expiring')->get();

        foreach ($jumps as $jump) {
            $this->gradeAllAttemptsForJump($jump);
            $jump->update(['status' => 'expired']);
            broadcast(new JumpExpired($jump));
            AnalyseJump::dispatch($jump->fresh());
        }

        // Also grade any finished attempts for already-expired jumps that were missed
        $ungradedAttempts = JumpAttempt::where('status', 'finished')
            ->with('jump')
            ->get()
            ->filter(fn ($a) => collect($a->question_list ?? [])->contains(fn ($q) => ($q['status'] ?? '') === 'pending'));

        $byJump = $ungradedAttempts->groupBy('jump_id');
        foreach ($byJump as $jumpId => $attempts) {
            $questionIds = $attempts->flatMap(fn ($a) => collect($a->question_list)->pluck('id'))->unique();
            $correctAnswers = Question::whereIn('id', $questionIds)->pluck('correct_answer', 'id');
            $questions = Question::whereIn('id', $questionIds)->get()->keyBy('id');
            foreach ($attempts as $attempt) {
                $this->gradeAttempt($attempt, $correctAnswers);
                $this->updateMasteryAndDifficulty($attempt->fresh(), $questions);
            }
        }
    }

    private function gradeAllAttemptsForJump(Jump $jump): void
    {
        $attempts = JumpAttempt::where('jump_id', $jump->id)->get();

        $questionIds = $attempts->flatMap(fn ($a) => collect($a->question_list)->pluck('id'))->unique();
        $correctAnswers = Question::whereIn('id', $questionIds)->pluck('correct_answer', 'id');
        $questions = Question::whereIn('id', $questionIds)->get()->keyBy('id');

        foreach ($attempts as $attempt) {
            if ($attempt->status === 'inProgress') {
                $attempt->update(['status' => 'finished', 'termination' => 'timeout']);
            }
            $this->gradeAttempt($attempt, $correctAnswers);
            $this->updateMasteryAndDifficulty($attempt->fresh(), $questions);
        }
    }

    /**
     * @param  Collection<int, string>  $correctAnswers
     */
    private function gradeAttempt(JumpAttempt $attempt, Collection $correctAnswers): void
    {
        $questionList = $attempt->question_list ?? [];
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

    /**
     * @param  Collection<int, Question>  $questions
     */
    private function updateMasteryAndDifficulty(JumpAttempt $attempt, Collection $questions): void
    {
        $user = $attempt->user;

        if (! $user) {
            return;
        }

        $userMastery = $user->mastery ?? 0;

        foreach ($attempt->question_list ?? [] as $item) {
            $given = $item['answer'] ?? null;

            if ($given === null) {
                continue;
            }

            $question = $questions->get($item['id']);

            if (! $question) {
                continue;
            }

            $isCorrect = $item['status'] === 'correct';
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
    }
}
