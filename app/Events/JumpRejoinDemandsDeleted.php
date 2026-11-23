<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JumpRejoinDemandsDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<int>  $demandIds
     */
    public function __construct(
        public int $teacherId,
        public int $jumpId,
        public array $demandIds,
    ) {}

    public function broadcastAs(): string
    {
        return 'JumpRejoinDemandsDeleted';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->teacherId),
        ];
    }

    /**
     * @return array{jump_id: int, demand_ids: list<int>}
     */
    public function broadcastWith(): array
    {
        return [
            'jump_id' => $this->jumpId,
            'demand_ids' => $this->demandIds,
        ];
    }
}
