<?php

namespace App\Models;

use App\Enums\WaiverType;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A discount on one fee head for one enrollment, applied when invoices are
 * generated (existing invoices keep the waiver they were issued with).
 */
class FeeWaiver extends Model
{
    use BelongsToSchool;

    protected $table = 'student_fee_waivers';

    protected $fillable = ['enrollment_id', 'fee_head_id', 'type', 'value', 'reason'];

    protected function casts(): array
    {
        return [
            'type' => WaiverType::class,
            'value' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<FeeHead, $this>
     */
    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }
}
