<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class JumpAttempt extends Model
{
    protected $table = 'jump_user';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'jump_id',
        'user_id',
        'question_list',
        'score',
        'status',
        'timer',
        'extra_time',
        'termination',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'question_list' => 'array',
            'score' => 'integer',
            'timer' => 'integer',
            'extra_time' => 'integer',
        ];
    }

    public function jump(): BelongsTo
    {
        return $this->belongsTo(Jump::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rejoinDemand(): HasOne
    {
        return $this->hasOne(JumpRejoinDemand::class);
    }

    /**
     * @param  Collection<int, string>|null  $correctAnswers
     * @return array{correct: int, incorrect: int, unanswered: int, total: int}
     */
    public function answerBreakdown(?Collection $correctAnswers = null): array
    {
        $questionList = $this->question_list ?? [];
        $questionIds = collect($questionList)->pluck('id')->filter()->unique();

        if ($correctAnswers === null && $questionIds->isNotEmpty()) {
            $correctAnswers = Question::query()->whereIn('id', $questionIds)->pluck('correct_answer', 'id');
        }

        $correctAnswers ??= collect();
        $correct = 0;
        $incorrect = 0;
        $unanswered = 0;

        foreach ($questionList as $item) {
            $answer = $item['answer'] ?? null;

            if ($answer === null || $answer === '') {
                $unanswered++;

                continue;
            }

            $status = $item['status'] ?? null;

            if ($status === 'correct') {
                $correct++;

                continue;
            }

            if ($status === 'incorrect') {
                $incorrect++;

                continue;
            }

            $expected = $item['correct_answer'] ?? $correctAnswers->get($item['id'] ?? null);

            if ($expected !== null && $answer === $expected) {
                $correct++;
            } else {
                $incorrect++;
            }
        }

        return [
            'correct' => $correct,
            'incorrect' => $incorrect,
            'unanswered' => $unanswered,
            'total' => count($questionList),
        ];
    }
}
