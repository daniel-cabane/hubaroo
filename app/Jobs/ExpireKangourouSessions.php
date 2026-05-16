<?php

namespace App\Jobs;

use App\Events\SessionExpired;
use App\Models\Attempt;
use App\Models\Division;
use App\Models\KangourouSession;
use App\Services\GradingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireKangourouSessions implements ShouldQueue
{
    use Queueable;

    public function handle(GradingService $gradingService): void
    {
        $sessions = KangourouSession::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($sessions as $session) {
            $session->update(['status' => 'expired']);

            Attempt::where('kangourou_session_id', $session->id)
                ->where('status', 'inProgress')
                ->each(function (Attempt $attempt) use ($gradingService): void {
                    $attempt->update(['termination' => 'timeout']);
                    $gradingService->gradeAndSave($attempt);
                });

            $this->computeAnalysis($session);

            broadcast(new SessionExpired($session));
        }
    }

    private function computeAnalysis(KangourouSession $session): void
    {
        $questions = $session->paper->questions()->orderByPivot('order')->get();

        $session->divisions->each(function (Division $division) use ($session, $questions): void {
            $studentIds = $division->students()->pluck('users.id');

            $attempts = Attempt::where('kangourou_session_id', $session->id)
                ->whereIn('user_id', $studentIds)
                ->where('status', 'finished')
                ->get();

            if ($attempts->isEmpty()) {
                return;
            }

            $totalAttempts = $attempts->count();

            $analysis = $questions->values()->map(function ($question, int $index) use ($attempts, $totalAttempts): array {
                $correctCount = $attempts->filter(function (Attempt $attempt) use ($index): bool {
                    $answers = $attempt->answers ?? [];

                    return isset($answers[$index]) && ($answers[$index]['status'] ?? '') === 'correct';
                })->count();

                return [
                    'question_id' => $question->id,
                    'success_ratio' => round($correctCount / $totalAttempts, 2),
                    'reviewed' => false,
                ];
            })->all();

            $division->kangourouSessions()->updateExistingPivot($session->id, ['analysis' => $analysis]);
        });
    }
}
