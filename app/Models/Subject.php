<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = ['school_class_id', 'group_id', 'name_en', 'name_bn', 'code', 'combined_key', 'is_optional_allowed', 'display_order'];

    protected $attributes = [
        'is_optional_allowed' => false,
        'display_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_optional_allowed' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
