<?php

namespace App\Jobs;

use App\Events\PocketChanged;
use App\Services\JumpGradingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RegradeJumpScores implements ShouldQueue
{
    use Queueable;

    public const LOCK = 'jump-score-regrade';

    public const STATUS = 'jump-score-regrade-status';

    public int $tries = 1;

    public function handle(JumpGradingService $grading): void
    {
        try {
            $result = $grading->regradeExpiredAttempts();

            Cache::put(self::STATUS, [
                'state' => 'done',
                'jumps' => $result['jumps'],
                'attempts' => $result['attempts'],
            ], now()->addHour());

            PocketChanged::broadcastToDivisions($result['division_ids']);
        } catch (Throwable $exception) {
            report($exception);

            Cache::put(self::STATUS, [
                'state' => 'failed',
            ], now()->addHour());
        } finally {
            Cache::lock(self::LOCK)->forceRelease();
        }
    }
}
