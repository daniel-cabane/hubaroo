<?php

namespace App\Events;

use App\Models\Division;
use App\Models\Jump;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JumpActivated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Jump $jump,
        public Division $division,
    ) {}

    public function broadcastAs(): string
    {
        return 'JumpActivated';
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
            'jump' => [
                'id' => $this->jump->id,
                'course_id' => $this->jump->course_id,
                'nb_questions' => $this->jump->nb_questions,
                'time' => $this->jump->time,
                'status' => $this->jump->status,
                'expiration' => $this->jump->expiration,
                'growth' => $this->jump->growth,
                'course' => [
                    'id' => $this->jump->course->id,
                    'title' => $this->jump->course->title,
                ],
            ],
        ];
    }
}
