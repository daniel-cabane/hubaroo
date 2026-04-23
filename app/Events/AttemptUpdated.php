<?php

namespace App\Events;

use App\Models\Attempt;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttemptUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Attempt $attempt) {}

    public function broadcastAs(): string
    {
        return 'AttemptUpdated';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('session.'.$this->attempt->kangourou_session_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'attempt' => $this->attempt->load('user:id,name,email')->toArray(),
        ];
    }
}
