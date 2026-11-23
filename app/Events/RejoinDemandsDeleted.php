<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RejoinDemandsDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<int>  $demandIds
     */
    public function __construct(
        public int $authorId,
        public int $sessionId,
        public array $demandIds,
    ) {}

    public function broadcastAs(): string
    {
        return 'RejoinDemandsDeleted';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->authorId),
        ];
    }

    /**
     * @return array{session_id: int, demand_ids: list<int>}
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'demand_ids' => $this->demandIds,
        ];
    }
}
