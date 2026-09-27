<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use BelongsToSchool;

    protected $fillable = ['invoice_id', 'fee_head_id', 'amount', 'waiver_amount', 'net_amount'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'waiver_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<FeeHead, $this>
     */
    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }
}
