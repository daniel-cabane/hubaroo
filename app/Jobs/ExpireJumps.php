<?php

namespace App\Jobs;

use App\Events\JumpExpired;
use App\Models\Jump;
use App\Models\JumpAttempt;
use App\Models\Question;
use App\Models\User;
use App\Services\JumpGradingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExpireJumps implements ShouldQueue
{
    use Queueable;

    public function handle(?JumpGradingService $jumpGradingService = null): void
    {
        $jumpGradingService ??= app(JumpGradingService::class);

        // Transition active jumps that have reached their expiration to 'expiring'
        Jump::where('status', 'active')
            ->where('expiration', '<=', now())
            ->update(['status' => 'expiring']);

        // Grade all expiring jumps (auto-expired above + manually set to 'expiring' by teacher)
        $jumps = Jump::where('status', 'expiring')->get();

        foreach ($jumps as $jump) {
            $this->gradeAllAttemptsForJump($jump, $jumpGradingService);
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
                $jumpGradingService->gradeAttempt($attempt, $correctAnswers);
                $this->updateMasteryAndDifficulty($attempt->fresh(), $questions);
            }
        }
    }

    private function gradeAllAttemptsForJump(Jump $jump, JumpGradingService $jumpGradingService): void
    {
        $attempts = JumpAttempt::where('jump_id', $jump->id)->get();

        $questionIds = $attempts->flatMap(fn ($a) => collect($a->question_list)->pluck('id'))->unique();
        $correctAnswers = Question::whereIn('id', $questionIds)->pluck('correct_answer', 'id');
        $questions = Question::whereIn('id', $questionIds)->get()->keyBy('id');

        foreach ($attempts as $attempt) {
            if ($attempt->status === 'inProgress') {
                $attempt->update(['status' => 'finished', 'termination' => 'timeout']);
            }
            $jumpGradingService->gradeAttempt($attempt, $correctAnswers);
            $this->updateMasteryAndDifficulty($attempt->fresh(), $questions);
        }
    }

    /**
     * @param  Collection<int, Question>  $questions
     */
    private function updateMasteryAndDifficulty(JumpAttempt $attempt, Collection $questions): void
    {
        $userId = $attempt->user_id;

        if (! $userId) {
            return;
        }

        DB::transaction(function () use ($userId, $attempt, $questions): void {
            $user = User::lockForUpdate()->find($userId);

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
        });
    }
}
