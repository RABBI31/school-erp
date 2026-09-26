<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class GuardianStudent extends Pivot
{
    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
