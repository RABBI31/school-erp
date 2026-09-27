<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money received from a guardian, allocated to invoices oldest first. Never
 * deleted: a cancelled payment is soft-deleted with a reason.
 */
class Payment extends Model
{
    use BelongsToSchool, SoftDeletes;

    protected $fillable = ['receipt_no', 'enrollment_id', 'paid_at', 'amount', 'method', 'transaction_ref', 'received_by', 'note'];

    protected function casts(): array
    {
        return [
            'paid_at' => 'immutable_datetime',
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
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
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
