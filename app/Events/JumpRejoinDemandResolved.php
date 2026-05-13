<?php

namespace App\Events;

use App\Models\JumpRejoinDemand;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JumpRejoinDemandResolved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public JumpRejoinDemand $demand,
        public string $resolution,
        public int $extraTime = 0,
    ) {}

    public function broadcastAs(): string
    {
        return 'JumpRejoinDemandResolved';
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('jump-rejoin.'.$this->demand->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'demand_id' => $this->demand->id,
            'resolution' => $this->resolution,
            'attempt_id' => $this->demand->jump_attempt_id,
            'extra_time' => $this->extraTime,
        ];
    }
}
