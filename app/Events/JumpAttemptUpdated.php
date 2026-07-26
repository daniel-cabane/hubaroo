<?php

namespace App\Events;

use App\Models\JumpAttempt;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JumpAttemptUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public JumpAttempt $jumpAttempt) {}

    public function broadcastAs(): string
    {
        return 'JumpAttemptUpdated';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('jump.'.$this->jumpAttempt->jump_id),
        ];
    }

    public function broadcastWith(): array
    {
        $questionList = $this->jumpAttempt->question_list ?? [];
        $answeredCount = collect($questionList)
            ->filter(fn (array $question): bool => array_key_exists('answer', $question) && $question['answer'] !== null && $question['answer'] !== '')
            ->count();

        return [
            'attempt' => [
                'id' => $this->jumpAttempt->id,
                'jump_id' => $this->jumpAttempt->jump_id,
                'user_id' => $this->jumpAttempt->user_id,
                'status' => $this->jumpAttempt->status,
                'termination' => $this->jumpAttempt->termination,
                'timer' => $this->jumpAttempt->timer,
                'answered_count' => $answeredCount,
                'total_questions' => count($questionList),
                'score' => $this->jumpAttempt->status === 'finished' ? $this->jumpAttempt->score : null,
                'updated_at' => $this->jumpAttempt->updated_at?->toJSON(),
            ],
        ];
    }
}
