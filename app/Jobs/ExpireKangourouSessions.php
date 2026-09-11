<?php

namespace App\Jobs;

use App\Events\SessionExpired;
use App\Models\Attempt;
use App\Models\KangourouSession;
use App\Services\GradingService;
use App\Services\SessionAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireKangourouSessions implements ShouldQueue
{
    use Queueable;

    public function handle(GradingService $gradingService, ?SessionAnalysisService $sessionAnalysisService = null): void
    {
        $sessionAnalysisService ??= app(SessionAnalysisService::class);
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
                ->each(function (Attempt $attempt) use ($gradingService): void {
                    $needsGrading = $attempt->status === 'inProgress' || $attempt->score === null;

                    if ($attempt->status === 'inProgress') {
                        $attempt->update(['termination' => 'timeout']);
                    }

                    if (! $needsGrading) {
                        return;
                    }

                    $gradingService->gradeAndSave($attempt);
                    UpdateMasteryAndDifficulty::dispatch($attempt);
                });

            $sessionAnalysisService->compute($session);

            broadcast(new SessionExpired($session));
        }
    }
}
