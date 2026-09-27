<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subject in an exam with its full and pass marks. When parts (CQ / MCQ /
 * practical) are used, each used part has its own full and pass marks and
 * total_full is their sum.
 */
class ExamSubject extends Model
{
    use BelongsToSchool;

    public const PARTS = ['cq', 'mcq', 'practical'];

    protected $fillable = [
        'exam_id', 'subject_id', 'school_class_id',
        'cq_full', 'cq_pass', 'mcq_full', 'mcq_pass', 'practical_full', 'practical_pass',
        'total_full', 'total_pass',
    ];

    protected function casts(): array
    {
        return [
            'cq_full' => 'decimal:2',
            'cq_pass' => 'decimal:2',
            'mcq_full' => 'decimal:2',
            'mcq_pass' => 'decimal:2',
            'practical_full' => 'decimal:2',
            'practical_pass' => 'decimal:2',
            'total_full' => 'decimal:2',
            'total_pass' => 'decimal:2',
        ];
    }

    /**
     * The parts this subject uses (those with a full mark), e.g. ['cq', 'mcq'].
     *
     * @return list<string>
     */
    public function usedParts(): array
    {
        return array_values(array_filter(self::PARTS, fn (string $part) => $this->{"{$part}_full"} !== null));
    }

    /**
     * @return BelongsTo<Exam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return HasMany<Mark, $this>
     */
    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }
}
