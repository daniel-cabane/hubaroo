<?php

namespace App\Models;

use Database\Factories\JumpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jump extends Model
{
    /** @use HasFactory<JumpFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['rank'];

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

    public function getRankAttribute(): int
    {
        return Jump::where('course_id', $this->course_id)
            ->where('id', '<=', $this->id)
            ->count();
    }
}
