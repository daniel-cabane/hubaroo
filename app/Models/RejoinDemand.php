<?php

namespace App\Models;

use App\Events\RejoinDemandResolved;
use App\Events\RejoinDemandsDeleted;
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

    /**
     * Destroy every rejoin request for a session and tell its author to drop them.
     */
    public static function deleteForSession(KangourouSession $session): void
    {
        $demands = static::query()
            ->whereIn('attempt_id', $session->attempts()->select('id'))
            ->orderBy('id')
            ->get();

        if ($demands->isEmpty()) {
            return;
        }

        foreach ($demands as $demand) {
            broadcast(new RejoinDemandResolved($demand, 'expired'));
        }

        $demandIds = $demands->map(fn (self $demand): int => (int) $demand->id)->all();

        static::query()->whereKey($demandIds)->delete();

        if ($session->author_id === null) {
            return;
        }

        broadcast(new RejoinDemandsDeleted((int) $session->author_id, $session->id, $demandIds));
    }
}
