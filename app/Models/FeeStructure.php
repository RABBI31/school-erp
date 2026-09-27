<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The amount a class pays for a fee head in an academic year.
 */
class FeeStructure extends Model
{
    use BelongsToSchool;

    protected $fillable = ['academic_year_id', 'school_class_id', 'fee_head_id', 'amount'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<FeeHead, $this>
     */
    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }
}
