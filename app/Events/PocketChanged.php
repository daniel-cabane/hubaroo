<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class PocketChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ?int $divisionId = null,
        public ?int $userId = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'PocketChanged';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        if ($this->userId !== null) {
            return [new PrivateChannel('App.Models.User.'.$this->userId)];
        }

        return [new PrivateChannel('division.'.$this->divisionId)];
    }

    /**
     * @return array{division_id: int|null}
     */
    public function broadcastWith(): array
    {
        return [
            'division_id' => $this->divisionId,
        ];
    }

    /**
     * @param  iterable<int, int|string|null>  $divisionIds
     */
    public static function broadcastToDivisions(iterable $divisionIds): void
    {
        Collection::make($divisionIds)
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->each(function (int $divisionId): void {
                broadcast(new self(divisionId: $divisionId));
            });
    }
}
