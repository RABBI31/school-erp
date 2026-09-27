<?php

namespace App\Services\Fees;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\CurrentSchool;
use App\Support\Money;
use Illuminate\Support\Facades\Storage;

/**
 * Everything the receipt PDF shows, already formatted for the UI language
 * (Bangla digits in bn).
 */
class ReceiptData
{
    public function __construct(private CurrentSchool $currentSchool) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Payment $payment): array
    {
        $payment->load([
            'enrollment.student:id,student_uid,name_en,name_bn',
            'enrollment.section:id,name,school_class_id',
            'enrollment.section.schoolClass:id,name_en,name_bn',
            'allocations.invoice.items.feeHead:id,name_en,name_bn',
            'receiver:id,name',
        ]);

        $school = $this->currentSchool->get();
        $bn = app()->getLocale() === 'bn';
        $digits = fn (string|int $value) => $bn ? bn_digits($value) : (string) $value;
        $money = fn (string|int $amount) => Money::format($amount, $bn);
        $pick = fn (?string $en, ?string $bnText) => $bn ? ($bnText ?: $en) : $en;
        $student = $payment->enrollment->student;
        $class = $payment->enrollment->section->schoolClass;
        $due = Invoice::query()->where('enrollment_id', $payment->enrollment_id)->outstanding()->get()->sum(fn (Invoice $i) => $i->duePaisa());

        return [
            'bn' => $bn,
            'cancelled' => $payment->trashed(),
            'school' => [
                'name' => $pick($school->name_en, $school->name_bn),
                'address' => $school->address,
                'phone' => $school->phone ? $digits($school->phone) : null,
                'eiin' => $school->eiin ? $digits($school->eiin) : null,
                'logo' => $this->logo($school->logo_path),
            ],
            'receipt_no' => $payment->receipt_no,
            'date' => $digits($payment->paid_at->format('d/m/Y H:i')),
            'student' => [
                'name' => $pick($student->name_en, $student->name_bn),
                'uid' => $student->student_uid,
                'class' => $pick($class->name_en, $class->name_bn),
                'section' => $payment->enrollment->section->name,
                'roll' => $digits($payment->enrollment->roll),
            ],
            'lines' => $payment->allocations->map(fn (PaymentAllocation $allocation) => [
                'invoice_no' => $allocation->invoice->invoice_no,
                'month' => $allocation->invoice->month ? $digits($allocation->invoice->issue_date->format('m/Y')) : '',
                'heads' => $allocation->invoice->items->map(fn ($item) => $pick($item->feeHead->name_en, $item->feeHead->name_bn))->join(', '),
                'amount' => $money($allocation->amount),
            ])->all(),
            'amount' => $money($payment->amount),
            'method' => __('messages.payment_methods.'.$payment->method->value),
            'transaction_ref' => $payment->transaction_ref,
            'received_by' => $payment->receiver?->name,
            'due_now' => $money(Money::fromPaisa($due)),
        ];
    }

    private function logo(?string $path): ?string
    {
        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return 'data:'.Storage::disk('public')->mimeType($path).';base64,'.base64_encode(Storage::disk('public')->get($path));
    }
}
