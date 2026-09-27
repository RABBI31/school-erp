<?php

namespace App\Models;

use App\Enums\ExamStatus;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\ExamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Status: draft (setting subjects) → marks_entry → published (marks locked).
 */
class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use BelongsToSchool, HasFactory;

    protected $fillable = ['academic_year_id', 'name_en', 'name_bn', 'grading_scheme_id', 'start_date'];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date:Y-m-d',
            'status' => ExamStatus::class,
            'published_at' => 'immutable_datetime',
        ];
    }

    public function isPublished(): bool
    {
        return $this->status === ExamStatus::Published;
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<GradingScheme, $this>
     */
    public function gradingScheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class);
    }

    /**
     * @return HasMany<ExamSubject, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(ExamSubject::class);
    }

    /**
     * @return HasMany<ExamResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(ExamResult::class);
    }
}
