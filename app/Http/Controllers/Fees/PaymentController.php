<?php

namespace App\Http\Controllers\Fees;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\CancelPaymentRequest;
use App\Http\Requests\Fees\CollectPaymentRequest;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Services\Fees\PaymentService;
use App\Services\Fees\ReceiptData;
use App\Services\Pdf\PdfRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function store(CollectPaymentRequest $request): RedirectResponse
    {
        $enrollment = Enrollment::query()->findOrFail($request->validated('enrollment_id'));

        $payment = $this->payments->collect(
            $enrollment,
            (string) $request->validated('amount'),
            PaymentMethod::from($request->validated('method')),
            $request->user(),
            $request->validated('transaction_ref'),
            $request->validated('note'),
        );

        return to_route('fees.collect', ['enrollment_id' => $enrollment->id])
            ->with('success', __('messages.payment_saved', ['receipt' => $payment->receipt_no]))
            ->with('payment_id', $payment->id);
    }

    public function cancel(CancelPaymentRequest $request, Payment $payment): RedirectResponse
    {
        $this->payments->cancel($payment, $request->validated('reason'), $request->user());

        return back()->with('success', __('messages.payment_cancelled', ['receipt' => $payment->receipt_no]));
    }

    /**
     * The receipt as a PDF (half A4 = A5), in the UI language. A cancelled
     * payment's receipt can still be opened; it is stamped as cancelled.
     */
    public function receipt(Payment $payment, ReceiptData $receipt, PdfRenderer $pdf): Response
    {
        return response($pdf->render('pdf.receipt', $receipt->for($payment), 'A5'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$payment->receipt_no.'.pdf"',
        ]);
    }
}
