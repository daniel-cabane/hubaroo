<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JumpRejoinDemand extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'jump_attempt_id',
    ];

    public function jumpAttempt(): BelongsTo
    {
        return $this->belongsTo(JumpAttempt::class);
    }
}
