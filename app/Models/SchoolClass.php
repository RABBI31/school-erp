<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    /** @use HasFactory<SchoolClassFactory> */
    use BelongsToSchool, HasFactory;

    protected $table = 'classes';

    protected $fillable = ['name_en', 'name_bn', 'numeric_order', 'has_groups'];

    protected $attributes = [
        'has_groups' => false,
    ];

    protected function casts(): array
    {
        return [
            'numeric_order' => 'integer',
            'has_groups' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /**
     * @return HasMany<Subject, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }
}
