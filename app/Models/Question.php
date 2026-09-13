<?php

namespace App\Models;

use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'image',
        'correct_answer',
        'tier',
        'difficulty',
    ];

    /** @var array<string, int> */
    public const LEVEL_VALUES = [
        'e' => 1,
        'b' => 2,
        'c' => 3,
        'j' => 4,
        'p' => 4,
        's' => 5,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tier' => 'integer',
            'difficulty' => 'integer',
        ];
    }

    public static function calculateDifficulty(int $tier, int $level = 1): int
    {
        return 300 * max($level, 1) + 100 * (int) round(pow(2, max($tier, 1) - 1));
    }

    /**
     * Get the papers for this question.
     */
    public function papers(): BelongsToMany
    {
        return $this->belongsToMany(Paper::class, 'paper_question')
            ->withPivot('order')
            ->withTimestamps();
    }
}
