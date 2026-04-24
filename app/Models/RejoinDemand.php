<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RejoinDemand extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'attempt_id',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }
}
