<?php

namespace App\Jobs;

use App\Events\SessionExpired;
use App\Models\Attempt;
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

            broadcast(new SessionExpired($session));
        }
    }
}
