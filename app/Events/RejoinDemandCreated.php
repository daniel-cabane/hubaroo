<?php

namespace App\Events;

use App\Models\RejoinDemand;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RejoinDemandCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public RejoinDemand $demand) {}

    public function broadcastAs(): string
    {
        return 'RejoinDemandCreated';
    }

    public function broadcastOn(): array
    {
        $authorId = $this->demand->attempt->kangourouSession->author_id;

        return [
            new PrivateChannel('App.Models.User.'.$authorId),
        ];
    }

    public function broadcastWith(): array
    {
        $attempt = $this->demand->attempt->load('kangourouSession.paper');
        $answeredCount = collect($attempt->answers)->filter(fn ($a) => $a['answer'] !== null)->count();

        return [
            'demand' => [
                'id' => $this->demand->id,
                'created_at' => $this->demand->created_at,
                'attempt' => [
                    'id' => $attempt->id,
                    'name' => $attempt->name,
                    'timer' => $attempt->timer,
                    'extra_time' => $attempt->extra_time,
                    'termination' => $attempt->termination,
                    'status' => $attempt->status,
                    'answered_count' => $answeredCount,
                    'updated_at' => $attempt->updated_at,
                    'session' => [
                        'id' => $attempt->kangourouSession->id,
                        'code' => $attempt->kangourouSession->code,
                        'paper_title' => $attempt->kangourouSession->paper?->title,
                        'preferences' => $attempt->kangourouSession->preferences,
                    ],
                ],
            ],
        ];
    }
}
