<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AcademicYearFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    /** @use HasFactory<AcademicYearFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = ['name', 'starts_on', 'ends_on'];

    protected $attributes = [
        'is_current' => false,
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date:Y-m-d',
            'ends_on' => 'immutable_date:Y-m-d',
            'is_current' => 'boolean',
        ];
    }
}
