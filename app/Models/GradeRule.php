<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class GradeRule extends Model
{
    use BelongsToSchool;

    protected $fillable = ['grading_scheme_id', 'min_percent', 'max_percent', 'letter', 'point'];

    protected function casts(): array
    {
        return [
            'min_percent' => 'decimal:2',
            'max_percent' => 'decimal:2',
            'point' => 'decimal:2',
        ];
    }
}
