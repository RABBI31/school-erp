<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingScheme extends Model
{
    use BelongsToSchool;

    protected $fillable = ['name', 'is_default'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return HasMany<GradeRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(GradeRule::class)->orderByDesc('min_percent');
    }
}
