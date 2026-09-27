<?php

namespace App\Services\Fees;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Collecting and cancelling payments (business-rules §3). A payment is
 * allocated to the enrollment's unpaid invoices, oldest first; paying more
 * than is due is refused (no advance payments in the MVP).
 */
class PaymentService
{
    public function __construct(private DocumentNumber $numbers) {}

    public function collect(Enrollment $enrollment, string|int $amount, PaymentMethod $method, User $receivedBy, ?string $transactionRef = null, ?string $note = null): Payment
    {
        $paisa = Money::toPaisa($amount);

        if ($paisa < 100) {
            throw new BusinessRuleException(__('messages.payment_too_small'));
        }

        return DB::transaction(function () use ($enrollment, $paisa, $method, $receivedBy, $transactionRef, $note) {
            $paidAt = now();
            [$receiptNo] = $this->numbers->take(DocumentNumber::RECEIPT, $paidAt->year);

            $invoices = Invoice::query()
                ->where('enrollment_id', $enrollment->id)
                ->outstanding()
                ->orderBy('due_date')->orderBy('id')
                ->lockForUpdate()
                ->get();

            $due = $invoices->sum(fn (Invoice $invoice) => $invoice->duePaisa());

            if ($paisa > $due) {
                throw new BusinessRuleException(__('messages.payment_over_due', ['due' => Money::fromPaisa($due)]));
            }

            $payment = Payment::create([
                'receipt_no' => $receiptNo,
                'enrollment_id' => $enrollment->id,
                'paid_at' => $paidAt,
                'amount' => Money::fromPaisa($paisa),
                'method' => $method,
                'transaction_ref' => $transactionRef,
                'received_by' => $receivedBy->id,
                'note' => $note,
            ]);

            $left = $paisa;
            foreach ($invoices as $invoice) {
                if ($left === 0) {
                    break;
                }

                $part = min($left, $invoice->duePaisa());
                $invoice->applyPaid(Money::toPaisa($invoice->paid_total) + $part);
                $invoice->save();
                $payment->allocations()->create(['invoice_id' => $invoice->id, 'amount' => Money::fromPaisa($part)]);
                $left -= $part;
            }

            return $payment;
        });
    }

    /**
     * Soft-delete the payment and take its allocations back off the invoices.
     * The allocation rows stay, as a record of what the payment had covered.
     */
    public function cancel(Payment $payment, string $reason, User $cancelledBy): void
    {
        DB::transaction(function () use ($payment, $reason, $cancelledBy) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $allocations = $payment->allocations()->get();
            $invoices = Invoice::query()->whereIn('id', $allocations->pluck('invoice_id'))->lockForUpdate()->get()->keyBy('id');

            foreach ($allocations as $allocation) {
                $invoice = $invoices[$allocation->invoice_id];
                $invoice->applyPaid(Money::toPaisa($invoice->paid_total) - Money::toPaisa($allocation->amount));
                $invoice->save();
            }

            $payment->forceFill(['cancelled_by' => $cancelledBy->id, 'cancel_reason' => $reason])->save();
            $payment->delete();
        });
    }
}
