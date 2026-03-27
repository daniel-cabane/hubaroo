<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Paper extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'level',
        'year',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
        ];
    }

    /**
     * Get the questions for this paper.
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'paper_question')
            ->withPivot('order')
            ->orderByPivot('order')
            ->withTimestamps();
    }
}
