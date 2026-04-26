<?php

namespace App\Events;

use App\Models\Attempt;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttemptNameUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Attempt $attempt) {}

    public function broadcastAs(): string
    {
        return 'AttemptNameUpdated';
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('attempt.'.$this->attempt->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'name' => $this->attempt->name,
        ];
    }
}
