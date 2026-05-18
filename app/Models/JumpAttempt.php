<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
}
