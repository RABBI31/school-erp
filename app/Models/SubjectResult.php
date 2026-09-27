<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One subject in a published result. Combined papers share one row, keyed by
 * the first paper's subject and the combined_key.
 */
class SubjectResult extends Model
{
    use BelongsToSchool;

    protected $fillable = ['exam_result_id', 'subject_id', 'combined_key', 'marks', 'full_marks', 'percent', 'letter', 'point', 'is_pass', 'is_optional'];

    protected function casts(): array
    {
        return [
            'marks' => 'decimal:2',
            'full_marks' => 'decimal:2',
            'percent' => 'integer',
            'point' => 'decimal:2',
            'is_pass' => 'boolean',
            'is_optional' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
