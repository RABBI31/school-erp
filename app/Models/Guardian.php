<?php

namespace App\Models;

use App\Enums\GuardianRelation;
use App\Models\Concerns\BelongsToSchool;
use App\Support\Phone;
use Database\Factories\GuardianFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guardian extends Model
{
    /** @use HasFactory<GuardianFactory> */
    use BelongsToSchool, HasFactory, SoftDeletes;

    protected $fillable = ['name_en', 'name_bn', 'relation', 'phone', 'alt_phone', 'occupation', 'nid'];

    protected function casts(): array
    {
        return [
            'relation' => GuardianRelation::class,
        ];
    }

    /**
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)->using(GuardianStudent::class)->withPivot('is_primary')->withTimestamps();
    }

    protected function phone(): Attribute
    {
        return Attribute::set(fn (?string $value) => Phone::normalize($value));
    }

    protected function altPhone(): Attribute
    {
        return Attribute::set(fn (?string $value) => Phone::normalize($value));
    }
}
