<?php

namespace App\Models;

use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    protected $fillable = [
        'name_en',
        'name_bn',
        'slug',
        'eiin',
        'address',
        'phone',
        'email',
        'default_locale',
        'is_active',
    ];

    protected $attributes = [
        'default_locale' => 'bn',
        'is_active' => true,
        'sms_balance' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sms_balance' => 'integer',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
