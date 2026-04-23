<?php

namespace App\Events;

use App\Models\Division;
use App\Models\KangourouSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SessionClosedForDivision implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Division $division,
        public KangourouSession $session,
    ) {}

    public function broadcastAs(): string
    {
        return 'SessionClosedForDivision';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('division.'.$this->division->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'session' => [
                'id' => $this->session->id,
            ],
        ];
    }
}
