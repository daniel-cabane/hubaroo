<?php

namespace App\Events;

use App\Models\Division;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentJoinedDivision implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Division $division,
        public User $student,
    ) {}

    public function broadcastAs(): string
    {
        return 'StudentJoinedDivision';
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
            'student' => [
                'id' => $this->student->id,
                'name' => $this->student->name,
                'email' => $this->student->email,
            ],
        ];
    }
}
