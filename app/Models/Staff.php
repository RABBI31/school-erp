<?php

namespace App\Models;

use App\Enums\StaffStatus;
use App\Models\Concerns\BelongsToSchool;
use App\Support\Phone;
use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    /** @use HasFactory<StaffFactory> */
    use BelongsToSchool, HasFactory, SoftDeletes;

    protected $fillable = ['employee_code', 'name_en', 'name_bn', 'designation', 'phone', 'email', 'joining_date', 'status'];

    protected $attributes = [
        'status' => 'active',
    ];

    protected $hidden = ['photo_path'];

    protected function casts(): array
    {
        return [
            'joining_date' => 'immutable_date:Y-m-d',
            'status' => StaffStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ClassTeacher, $this>
     */
    public function classTeacherships(): HasMany
    {
        return $this->hasMany(ClassTeacher::class);
    }

    /**
     * @return HasMany<SubjectTeacher, $this>
     */
    public function subjectTeacherships(): HasMany
    {
        return $this->hasMany(SubjectTeacher::class);
    }

    protected function phone(): Attribute
    {
        return Attribute::set(fn (?string $value) => Phone::normalize($value));
    }

    protected function email(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null || $value === '' ? null : strtolower(trim($value)));
    }
}
