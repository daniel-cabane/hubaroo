<?php

namespace App\Models;

use Database\Factories\AttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Attempt extends Model
{
    /** @use HasFactory<AttemptFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kangourou_session_id',
        'user_id',
        'name',
        'code',
        'answers',
        'status',
        'score',
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
            'answers' => 'array',
            'score' => 'integer',
            'timer' => 'integer',
            'extra_time' => 'integer',
        ];
    }

    public function kangourouSession(): BelongsTo
    {
        return $this->belongsTo(KangourouSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rejoinDemand(): HasOne
    {
        return $this->hasOne(RejoinDemand::class);
    }

    /**
     * Generate default answers array for 26 questions.
     *
     * @return array<int, array{answer: null, status: string}>
     */
    public static function defaultAnswers(): array
    {
        return array_map(fn () => ['answer' => null, 'status' => 'unanswered'], range(1, 26));
    }

    public static function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
