<?php

namespace App\Models;

use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Enums\StudentStatus;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class, section, group and roll live on the Enrollment (one per year), never here.
 */
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use BelongsToSchool, HasFactory, SoftDeletes;

    protected $fillable = [
        'name_en',
        'name_bn',
        'date_of_birth',
        'gender',
        'blood_group',
        'religion',
        'father_name',
        'mother_name',
        'present_address',
        'permanent_address',
        'birth_reg_no',
        'admission_date',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected $hidden = ['photo_path'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'immutable_date:Y-m-d',
            'admission_date' => 'immutable_date:Y-m-d',
            'gender' => Gender::class,
            'blood_group' => BloodGroup::class,
            'religion' => Religion::class,
            'status' => StudentStatus::class,
        ];
    }

    /**
     * @return BelongsToMany<Guardian, $this>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)->using(GuardianStudent::class)->withPivot('is_primary')->withTimestamps();
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
