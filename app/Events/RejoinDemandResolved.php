<?php

namespace App\Events;

use App\Models\RejoinDemand;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RejoinDemandResolved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public RejoinDemand $demand,
        public string $resolution,
        public int $extraTime = 0,
    ) {}

    public function broadcastAs(): string
    {
        return 'RejoinDemandResolved';
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('rejoin.'.$this->demand->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'demand_id' => $this->demand->id,
            'resolution' => $this->resolution,
            'attempt_id' => $this->demand->attempt_id,
            'extra_time' => $this->extraTime,
        ];
    }
}
