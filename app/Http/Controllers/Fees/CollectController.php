<?php

namespace App\Http\Controllers\Fees;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\CurrentAcademicYear;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The collect screen: search a student, see what is due, take the money.
 */
class CollectController extends Controller
{
    public function __construct(private CurrentAcademicYear $currentAcademicYear) {}

    public function __invoke(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $enrollment = $request->filled('enrollment_id') ? Enrollment::query()->findOrFail($request->integer('enrollment_id')) : null;

        return Inertia::render('Fees/Collect', [
            'search' => $search,
            'results' => $search !== '' ? $this->find($search) : [],
            'student' => $enrollment ? $this->student($enrollment) : null,
            'methods' => PaymentMethod::values(),
            'receiptId' => $request->session()->get('payment_id'),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function find(string $search): Collection
    {
        return Enrollment::query()
            ->where('academic_year_id', $this->currentAcademicYear->id())
            ->whereHas('student')
            ->where(fn (Builder $q) => $q
                ->when(ctype_digit($search), fn (Builder $q) => $q->orWhere('roll', (int) $search))
                ->orWhereHas('student', fn (Builder $s) => $s
                    ->whereLike('name_en', "%{$search}%")
                    ->orWhereLike('name_bn', "%{$search}%")
                    ->orWhereLike('student_uid', "%{$search}%")))
            ->with(['student:id,student_uid,name_en,name_bn', 'section:id,name,school_class_id', 'section.schoolClass:id,name_en,name_bn'])
            ->limit(10)
            ->get()
            ->map(fn (Enrollment $e) => [
                'enrollment_id' => $e->id,
                'roll' => $e->roll,
                ...$this->identity($e),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function student(Enrollment $enrollment): array
    {
        $enrollment->load(['student:id,student_uid,name_en,name_bn', 'student.guardians:id,name_en,name_bn,phone', 'section:id,name,school_class_id', 'section.schoolClass:id,name_en,name_bn']);

        $invoices = Invoice::query()
            ->where('enrollment_id', $enrollment->id)
            ->outstanding()
            ->with('items.feeHead:id,name_en,name_bn')
            ->orderBy('due_date')->orderBy('id')
            ->get();

        $guardian = $enrollment->student->guardians->firstWhere('pivot.is_primary', true);

        return [
            'enrollment_id' => $enrollment->id,
            'roll' => $enrollment->roll,
            ...$this->identity($enrollment),
            'guardian_phone' => $guardian?->phone,
            'due' => Money::fromPaisa($invoices->sum(fn (Invoice $i) => $i->duePaisa())),
            'invoices' => $invoices->map(fn (Invoice $i) => [
                'id' => $i->id,
                'invoice_no' => $i->invoice_no,
                'month' => $i->month,
                'due_date' => $i->due_date->toDateString(),
                'total' => $i->total,
                'paid_total' => $i->paid_total,
                'due' => Money::fromPaisa($i->duePaisa()),
                'status' => $i->status->value,
                'heads' => $i->items->map(fn ($item) => ['name_en' => $item->feeHead->name_en, 'name_bn' => $item->feeHead->name_bn, 'amount' => $item->net_amount]),
            ]),
            'payments' => Payment::query()
                ->where('enrollment_id', $enrollment->id)
                ->latest('paid_at')->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'receipt_no' => $p->receipt_no,
                    'paid_at' => $p->paid_at->toDateString(),
                    'amount' => $p->amount,
                    'method' => $p->method->value,
                ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function identity(Enrollment $e): array
    {
        return [
            'student_id' => $e->student->id,
            'student_uid' => $e->student->student_uid,
            'name_en' => $e->student->name_en,
            'name_bn' => $e->student->name_bn,
            'section' => $e->section->name,
            'class_name_en' => $e->section->schoolClass->name_en,
            'class_name_bn' => $e->section->schoolClass->name_bn,
        ];
    }
}
