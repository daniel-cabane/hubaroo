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
            ->with([
                'paper.questions' => fn ($q) => $q->orderByPivot('order'),
                'divisions.students',
            ])
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
        // Use eager-loaded questions if available (ordered from the load in handle())
        $questions = $session->paper->relationLoaded('questions')
            ? $session->paper->questions
            : $session->paper->questions()->orderByPivot('order')->get();

        // Load all finished attempts for this session once (avoids N+1 per division — W11)
        $allFinishedAttempts = Attempt::where('kangourou_session_id', $session->id)
            ->where('status', 'finished')
            ->get();

        $session->divisions->each(function (Division $division) use ($session, $questions, $allFinishedAttempts): void {
            // Use eager-loaded students if available
            $studentIds = $division->relationLoaded('students')
                ? $division->students->pluck('id')
                : $division->students()->pluck('users.id');

            $attempts = $allFinishedAttempts->whereIn('user_id', $studentIds->toArray())->values();

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
