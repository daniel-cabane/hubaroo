<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DivisionKangourouSession extends Pivot
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'analysis' => 'array',
        ];
    }
}
