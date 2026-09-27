<?php

namespace App\Services\Fees;

use App\Enums\InvoiceStatus;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The fee reports (business-rules §3): daily collection, dues list, monthly
 * summary. Amounts are decimal strings ("1250.00").
 */
class FeeReports
{
    /**
     * Payments received on a day, with totals by method and by collector.
     * Cancelled payments are listed (for the audit trail) but not counted.
     *
     * @return array{payments: Collection<int, array<string, mixed>>, by_method: array<string, string>, by_collector: array<string, string>, total: string}
     */
    public function daily(CarbonImmutable $date): array
    {
        $payments = Payment::withTrashed()
            ->whereBetween('paid_at', [$date->startOfDay(), $date->endOfDay()])
            ->with(['enrollment:id,student_id,section_id,roll', 'enrollment.student:id,student_uid,name_en,name_bn', 'enrollment.section:id,name,school_class_id', 'enrollment.section.schoolClass:id,name_en,name_bn', 'receiver:id,name', 'canceller:id,name'])
            ->orderBy('paid_at')->orderBy('id')
            ->get();

        $counted = $payments->reject(fn (Payment $p) => $p->trashed());
        $sum = fn (Collection $group) => Money::fromPaisa($group->sum(fn (Payment $p) => Money::toPaisa($p->amount)));

        return [
            'payments' => $payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'receipt_no' => $p->receipt_no,
                'time' => $p->paid_at->format('H:i'),
                'student_id' => $p->enrollment->student_id,
                'name_en' => $p->enrollment->student->name_en,
                'name_bn' => $p->enrollment->student->name_bn,
                'class_name_en' => $p->enrollment->section->schoolClass->name_en,
                'class_name_bn' => $p->enrollment->section->schoolClass->name_bn,
                'section' => $p->enrollment->section->name,
                'roll' => $p->enrollment->roll,
                'amount' => $p->amount,
                'method' => $p->method->value,
                'transaction_ref' => $p->transaction_ref,
                'received_by' => $p->receiver?->name,
                'cancelled' => $p->trashed(),
                'cancel_reason' => $p->cancel_reason,
                'cancelled_by' => $p->canceller?->name,
            ])->values(),
            'by_method' => $counted->groupBy(fn (Payment $p) => $p->method->value)->map($sum)->all(),
            'by_collector' => $counted->groupBy(fn (Payment $p) => $p->receiver?->name ?? '—')->map($sum)->all(),
            'total' => $sum($counted),
        ];
    }

    /**
     * Students of a year who owe money, in class / section / roll order, with
     * the primary guardian's phone.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function dues(?int $academicYearId, ?int $classId = null, ?int $sectionId = null): Collection
    {
        $totals = Invoice::query()
            ->outstanding()
            ->whereHas('enrollment', fn (Builder $query) => $query
                ->where('academic_year_id', $academicYearId)
                ->when($sectionId, fn (Builder $q) => $q->where('section_id', $sectionId))
                ->when($classId, fn (Builder $q) => $q->whereHas('section', fn (Builder $s) => $s->where('school_class_id', $classId))))
            ->groupBy('enrollment_id')
            ->get([
                'enrollment_id',
                DB::raw('SUM(total - paid_total) AS due'),
                DB::raw('COUNT(*) AS invoice_count'),
                DB::raw('MIN(month) AS since'),
                DB::raw('MAX(due_date) AS last_due_date'),
            ])
            ->keyBy('enrollment_id');

        return Enrollment::query()
            ->whereIn('id', $totals->keys())
            ->with(['student:id,student_uid,name_en,name_bn', 'student.guardians:id,phone', 'section:id,name,school_class_id', 'section.schoolClass:id,name_en,name_bn,numeric_order'])
            ->get()
            ->sortBy([['section.schoolClass.numeric_order', 'asc'], ['section.name', 'asc'], ['roll', 'asc']])
            ->values()
            ->map(function (Enrollment $e) use ($totals) {
                $row = $totals[$e->id];

                return [
                    'enrollment_id' => $e->id,
                    'student_id' => $e->student_id,
                    'student_uid' => $e->student->student_uid,
                    'name_en' => $e->student->name_en,
                    'name_bn' => $e->student->name_bn,
                    'class_name_en' => $e->section->schoolClass->name_en,
                    'class_name_bn' => $e->section->schoolClass->name_bn,
                    'section' => $e->section->name,
                    'roll' => $e->roll,
                    'guardian_phone' => $e->student->guardians->firstWhere('pivot.is_primary', true)?->phone,
                    'due' => Money::fromPaisa(Money::toPaisa($row->getRawOriginal('due'))),
                    'invoice_count' => (int) $row->getRawOriginal('invoice_count'),
                    'since' => $row->getRawOriginal('since'),
                    'last_due_date' => $row->getRawOriginal('last_due_date'),
                ];
            });
    }

    /**
     * One month: what its invoices billed, waived and still owe (per class),
     * and what was collected during the month (by method).
     *
     * @param  string  $month  YYYY-MM
     * @return array{classes: Collection<int, array<string, mixed>>, billed: array<string, string>, collected: array<string, string>, collected_total: string}
     */
    public function monthly(string $month): array
    {
        $classes = Invoice::query()
            ->join('enrollments', 'enrollments.id', '=', 'invoices.enrollment_id')
            ->join('sections', 'sections.id', '=', 'enrollments.section_id')
            ->join('classes', 'classes.id', '=', 'sections.school_class_id')
            ->where('invoices.month', $month)
            ->where('invoices.status', '!=', InvoiceStatus::Cancelled->value)
            ->groupBy('classes.id', 'classes.name_en', 'classes.name_bn', 'classes.numeric_order')
            ->orderBy('classes.numeric_order')
            ->toBase()
            ->get([
                'classes.id', 'classes.name_en', 'classes.name_bn',
                DB::raw('COUNT(*) AS invoices'),
                DB::raw('SUM(invoices.subtotal) AS subtotal'),
                DB::raw('SUM(invoices.waiver_total) AS waived'),
                DB::raw('SUM(invoices.total) AS total'),
                DB::raw('SUM(invoices.paid_total) AS paid'),
                DB::raw('SUM(CASE WHEN invoices.status = \'paid\' THEN 1 ELSE 0 END) AS paid_invoices'),
            ])
            ->map(fn (object $row) => [
                'id' => $row->id,
                'name_en' => $row->name_en,
                'name_bn' => $row->name_bn,
                'invoices' => (int) $row->invoices,
                'paid_invoices' => (int) $row->paid_invoices,
                'subtotal' => Money::fromPaisa(Money::toPaisa($row->subtotal)),
                'waived' => Money::fromPaisa(Money::toPaisa($row->waived)),
                'total' => Money::fromPaisa(Money::toPaisa($row->total)),
                'paid' => Money::fromPaisa(Money::toPaisa($row->paid)),
                'due' => Money::fromPaisa(Money::toPaisa($row->total) - Money::toPaisa($row->paid)),
            ]);

        $first = CarbonImmutable::createFromFormat('!Y-m', $month);
        $payments = Payment::query()->whereBetween('paid_at', [$first->startOfMonth(), $first->endOfMonth()])->get(['amount', 'method']);
        $sumOf = fn (string $key) => Money::fromPaisa($classes->sum(fn (array $row) => Money::toPaisa($row[$key])));

        return [
            'classes' => $classes,
            'billed' => [
                'subtotal' => $sumOf('subtotal'),
                'waived' => $sumOf('waived'),
                'total' => $sumOf('total'),
                'paid' => $sumOf('paid'),
                'due' => $sumOf('due'),
            ],
            'collected' => $payments->groupBy(fn (Payment $p) => $p->method->value)
                ->map(fn (Collection $group) => Money::fromPaisa($group->sum(fn (Payment $p) => Money::toPaisa($p->amount))))
                ->all(),
            'collected_total' => Money::fromPaisa($payments->sum(fn (Payment $p) => Money::toPaisa($p->amount))),
        ];
    }
}
