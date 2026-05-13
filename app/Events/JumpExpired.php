<?php

namespace App\Events;

use App\Models\Jump;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JumpExpired implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Jump $jump) {}

    public function broadcastAs(): string
    {
        return 'JumpExpired';
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('jump.'.$this->jump->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'jump_id' => $this->jump->id,
        ];
    }
}
