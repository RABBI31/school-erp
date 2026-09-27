<?php

namespace App\Models;

use App\Enums\FeeHeadType;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\FeeHeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Something a school charges for: tuition (monthly), session fee (yearly),
 * admission fee (one-time).
 */
class FeeHead extends Model
{
    /** @use HasFactory<FeeHeadFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = ['name_en', 'name_bn', 'type'];

    protected function casts(): array
    {
        return [
            'type' => FeeHeadType::class,
        ];
    }
}
