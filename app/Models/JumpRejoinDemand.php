<?php

namespace App\Models;

use App\Events\JumpRejoinDemandResolved;
use App\Events\JumpRejoinDemandsDeleted;
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

    /**
     * Destroy every rejoin request for a jump and tell the teacher to drop them.
     */
    public static function deleteForJump(Jump $jump): void
    {
        $demands = static::query()
            ->whereIn('jump_attempt_id', $jump->attempts()->select('id'))
            ->orderBy('id')
            ->get();

        if ($demands->isEmpty()) {
            return;
        }

        foreach ($demands as $demand) {
            broadcast(new JumpRejoinDemandResolved($demand, 'expired'));
        }

        $demandIds = $demands->map(fn (self $demand): int => (int) $demand->id)->all();

        static::query()->whereKey($demandIds)->delete();

        $jump->loadMissing('course.division');
        $teacherId = $jump->course?->division?->teacher_id;

        if ($teacherId === null) {
            return;
        }

        broadcast(new JumpRejoinDemandsDeleted((int) $teacherId, $jump->id, $demandIds));
    }
}
