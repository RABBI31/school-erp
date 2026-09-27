<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToSchool;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * What an enrollment owes: one per month for monthly fees. Payments are a
 * separate table; due = total − paid_total (waivers are already in total).
 */
class Invoice extends Model
{
    use BelongsToSchool, SoftDeletes;

    protected $fillable = [
        'enrollment_id', 'invoice_no', 'month', 'issue_date', 'due_date',
        'subtotal', 'waiver_total', 'fine_total', 'total', 'paid_total', 'status',
    ];

    protected $attributes = [
        'status' => 'unpaid',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'immutable_date:Y-m-d',
            'due_date' => 'immutable_date:Y-m-d',
            'subtotal' => 'decimal:2',
            'waiver_total' => 'decimal:2',
            'fine_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'status' => InvoiceStatus::class,
        ];
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public function scopeOutstanding(Builder $query): void
    {
        $query->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial]);
    }

    public function duePaisa(): int
    {
        if ($this->status === InvoiceStatus::Cancelled) {
            return 0;
        }

        return Money::toPaisa($this->total) - Money::toPaisa($this->paid_total);
    }

    /**
     * Set paid_total and the matching status (unpaid / partial / paid).
     */
    public function applyPaid(int $paidPaisa): void
    {
        $totalPaisa = Money::toPaisa($this->total);

        $this->paid_total = Money::fromPaisa($paidPaisa);
        $this->status = match (true) {
            $paidPaisa >= $totalPaisa => InvoiceStatus::Paid,
            $paidPaisa > 0 => InvoiceStatus::Partial,
            default => InvoiceStatus::Unpaid,
        };
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
