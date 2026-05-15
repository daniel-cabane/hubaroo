<?php

namespace App\Events;

use App\Models\JumpRejoinDemand;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JumpRejoinDemandCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public JumpRejoinDemand $demand) {}

    public function broadcastAs(): string
    {
        return 'JumpRejoinDemandCreated';
    }

    public function broadcastOn(): array
    {
        $teacherId = $this->demand->jumpAttempt->jump->course->division->teacher_id;

        return [
            new PrivateChannel('App.Models.User.'.$teacherId),
        ];
    }

    public function broadcastWith(): array
    {
        $attempt = $this->demand->jumpAttempt->load('user', 'jump.course');
        $answeredCount = collect($attempt->question_list)
            ->filter(fn ($q) => isset($q['answer']) && $q['answer'] !== null)
            ->count();

        return [
            'demand' => [
                'id' => $this->demand->id,
                'created_at' => $this->demand->created_at,
                'attempt' => [
                    'id' => $attempt->id,
                    'jump_id' => $attempt->jump_id,
                    'user' => [
                        'id' => $attempt->user->id,
                        'name' => $attempt->user->name,
                    ],
                    'timer' => $attempt->timer,
                    'extra_time' => $attempt->extra_time,
                    'termination' => $attempt->termination,
                    'status' => $attempt->status,
                    'answered_count' => $answeredCount,
                    'jump' => [
                        'id' => $attempt->jump->id,
                        'rank' => $attempt->jump->rank,
                        'course' => [
                            'title' => $attempt->jump->course->title,
                        ],
                    ],
                ],
            ],
        ];
    }
}
