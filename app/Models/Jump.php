<?php

namespace App\Models;

use Database\Factories\JumpFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jump extends Model
{
    /** @use HasFactory<JumpFactory> */
    use HasFactory;

    public const POST_EXPIRY_ANSWER_GRACE_SECONDS = 180;

    /**
     * @var list<string>
     */
    protected $appends = [];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'nb_questions',
        'time',
        'status',
        'expiration',
        'growth',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nb_questions' => 'integer',
            'time' => 'integer',
            'growth' => 'integer',
            'expiration' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'jump_user')
            ->withPivot('question_list', 'score', 'status', 'timer', 'extra_time', 'termination');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(JumpAttempt::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }

    /**
     * Learners must stop answering once expiration has passed or the jump is expiring/expired.
     */
    public function isClosed(): bool
    {
        if (in_array($this->status, ['expiring', 'expired'], true)) {
            return true;
        }

        return $this->expiration !== null && $this->expiration->isPast();
    }

    /**
     * Learners may still persist unsaved answers after the jump closes:
     * while status is expiring, or for a short window after expiration.
     */
    public function allowsLateAnswerSave(): bool
    {
        if (! $this->isClosed()) {
            return false;
        }

        if ($this->status === 'expiring') {
            return true;
        }

        if ($this->expiration === null) {
            return false;
        }

        return $this->expiration
            ->copy()
            ->addSeconds(self::POST_EXPIRY_ANSWER_GRACE_SECONDS)
            ->isFuture();
    }

    public function rank(): Attribute
    {
        return Attribute::make(
            get: fn () => Jump::where('course_id', $this->course_id)
                ->where('id', '<=', $this->id)
                ->count(),
        );
    }
}
