<?php

namespace App\Jobs;

use App\Models\KangourouSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireKangourouSessions implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        KangourouSession::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
    }
}
