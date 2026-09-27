<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Written only through MarkService, which audits every change.
 */
class Mark extends Model
{
    use BelongsToSchool;

    protected $fillable = ['exam_id', 'exam_subject_id', 'enrollment_id', 'cq', 'mcq', 'practical', 'total', 'is_absent', 'entered_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'cq' => 'decimal:2',
            'mcq' => 'decimal:2',
            'practical' => 'decimal:2',
            'total' => 'decimal:2',
            'is_absent' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ExamSubject, $this>
     */
    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class);
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return HasMany<MarkAudit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(MarkAudit::class);
    }
}
