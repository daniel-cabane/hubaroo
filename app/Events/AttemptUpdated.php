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

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $answers = $this->attempt->answers ?? [];
        $answeredCount = collect($answers)
            ->filter(fn (array $answer): bool => array_key_exists('answer', $answer) && $answer['answer'] !== null && $answer['answer'] !== '')
            ->count();

        return [
            'attempt' => [
                'id' => $this->attempt->id,
                'kangourou_session_id' => $this->attempt->kangourou_session_id,
                'user_id' => $this->attempt->user_id,
                'name' => $this->attempt->name,
                'status' => $this->attempt->status,
                'termination' => $this->attempt->termination,
                'timer' => $this->attempt->timer,
                'extra_time' => $this->attempt->extra_time,
                'answered_count' => $answeredCount,
                'total_questions' => count($answers),
                'score' => $this->attempt->status === 'finished' ? $this->attempt->score : null,
                'updated_at' => $this->attempt->updated_at?->toJSON(),
            ],
        ];
    }
}
