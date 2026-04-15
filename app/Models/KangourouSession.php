<?php

namespace App\Models;

use Database\Factories\KangourouSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class KangourouSession extends Model
{
    /** @use HasFactory<KangourouSessionFactory> */
    use HasFactory;

    public const DEFAULT_PREFERENCES = [
        'time_limit' => 50,
        'correction' => 'delayed',
        'grading' => [
            'tier1' => 3,
            'tier2' => 4,
            'tier3' => 5,
            'tier4_bonus' => 1,
            'penalty_fraction' => 0.25,
        ],
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'paper_id',
        'code',
        'author_id',
        'status',
        'privacy',
        'expires_at',
        'preferences',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferences' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function paper(): BelongsTo
    {
        return $this->belongsTo(Paper::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    public function divisions(): BelongsToMany
    {
        return $this->belongsToMany(Division::class);
    }

    /**
     * Get preferences merged with defaults.
     *
     * @return array<string, mixed>
     */
    public function getEffectivePreferences(): array
    {
        return array_replace_recursive(self::DEFAULT_PREFERENCES, $this->preferences ?? []);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || $this->expires_at->isPast();
    }

    public static function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
