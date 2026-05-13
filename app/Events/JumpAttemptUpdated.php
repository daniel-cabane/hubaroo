<?php

namespace App\Events;

use App\Models\JumpAttempt;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JumpAttemptUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public JumpAttempt $jumpAttempt) {}

    public function broadcastAs(): string
    {
        return 'JumpAttemptUpdated';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('jump.'.$this->jumpAttempt->jump_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'attempt' => $this->jumpAttempt->load('user:id,name,email')->toArray(),
        ];
    }
}
