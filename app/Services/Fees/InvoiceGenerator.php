<?php

namespace App\Services\Fees;

use App\Enums\EnrollmentStatus;
use App\Enums\FeeHeadType;
use App\Enums\InvoiceStatus;
use App\Enums\WaiverType;
use App\Exceptions\BusinessRuleException;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\FeeWaiver;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Monthly invoices for the current school (business-rules §3): one per active
 * enrollment of the year containing the month, with the monthly heads, yearly
 * heads not yet billed this enrollment, and one-time heads never billed to the
 * student. Waivers are applied here. Running a month twice creates nothing new.
 */
class InvoiceGenerator
{
    public function __construct(private DocumentNumber $numbers) {}

    /**
     * @param  string  $month  YYYY-MM
     * @return array{created: int, skipped: int}
     */
    public function generate(string $month): array
    {
        $first = CarbonImmutable::createFromFormat('!Y-m', $month);
        $year = AcademicYear::query()->whereDate('starts_on', '<=', $first)->whereDate('ends_on', '>=', $first)->first()
            ?? throw new BusinessRuleException(__('messages.fees_month_outside_year', ['month' => $month]));

        // One transaction, the school row locked first (by DocumentNumber):
        // an overlapping run waits, then finds these invoices and skips them.
        return DB::transaction(function () use ($month, $first, $year) {
            $this->numbers->lock();

            $enrollments = Enrollment::query()
                ->where('academic_year_id', $year->id)
                ->where('status', EnrollmentStatus::Active)
                ->with('section:id,school_class_id')
                ->orderBy('section_id')->orderBy('roll')
                ->get();

            $billedThisMonth = Invoice::withTrashed()->where('month', $month)->whereIn('enrollment_id', $enrollments->pluck('id'))->pluck('enrollment_id')->flip();
            $pending = $enrollments->reject(fn (Enrollment $e) => $billedThisMonth->has($e->id));

            $drafts = $this->drafts($pending, $year);
            $numbers = $this->numbers->take(DocumentNumber::INVOICE, $first->year, $drafts->count());

            foreach ($drafts->values() as $i => [$enrollment, $items]) {
                $this->createInvoice($enrollment, $items, $numbers[$i], $month, $first);
            }

            return ['created' => $drafts->count(), 'skipped' => $enrollments->count() - $drafts->count()];
        });
    }

    /**
     * The items each enrollment is billed, leaving out enrollments with nothing to bill.
     *
     * @param  Collection<int, Enrollment>  $enrollments
     * @return Collection<int, array{0: Enrollment, 1: list<array{fee_head_id: int, amount: int, waiver: int}>}>
     */
    private function drafts(Collection $enrollments, AcademicYear $year): Collection
    {
        $types = FeeHead::query()->pluck('type', 'id');
        $structure = FeeStructure::query()->where('academic_year_id', $year->id)->where('amount', '>', 0)->get()->groupBy('school_class_id');
        $waivers = FeeWaiver::query()->whereIn('enrollment_id', $enrollments->pluck('id'))->get()->keyBy(fn (FeeWaiver $w) => "{$w->enrollment_id}:{$w->fee_head_id}");
        [$yearlyBilled, $oneTimeBilled] = $this->alreadyBilled($enrollments);

        return $enrollments->map(function (Enrollment $enrollment) use ($types, $structure, $waivers, $yearlyBilled, $oneTimeBilled) {
            $items = [];

            foreach ($structure->get($enrollment->section->school_class_id, []) as $fee) {
                $billed = match ($types[$fee->fee_head_id]) {
                    FeeHeadType::Monthly => false,
                    FeeHeadType::Yearly => isset($yearlyBilled["{$enrollment->id}:{$fee->fee_head_id}"]),
                    FeeHeadType::OneTime => isset($oneTimeBilled["{$enrollment->student_id}:{$fee->fee_head_id}"]),
                };

                if (! $billed) {
                    $amount = Money::toPaisa($fee->amount);
                    $items[] = ['fee_head_id' => $fee->fee_head_id, 'amount' => $amount, 'waiver' => $this->waiver($waivers->get("{$enrollment->id}:{$fee->fee_head_id}"), $amount)];
                }
            }

            return $items === [] ? null : [$enrollment, $items];
        })->filter();
    }

    /**
     * Yearly heads already on an invoice of this enrollment ("enrollment:head")
     * and one-time heads already on any invoice of the student ("student:head").
     *
     * @param  Collection<int, Enrollment>  $enrollments
     * @return array{0: array<string, true>, 1: array<string, true>}
     */
    private function alreadyBilled(Collection $enrollments): array
    {
        $rows = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('enrollments', 'enrollments.id', '=', 'invoices.enrollment_id')
            ->join('fee_heads', 'fee_heads.id', '=', 'invoice_items.fee_head_id')
            ->whereIn('enrollments.student_id', $enrollments->pluck('student_id'))
            ->whereIn('fee_heads.type', [FeeHeadType::Yearly->value, FeeHeadType::OneTime->value])
            ->whereNull('invoices.deleted_at')
            ->where('invoices.status', '!=', InvoiceStatus::Cancelled->value)
            ->get(['invoices.enrollment_id', 'enrollments.student_id', 'invoice_items.fee_head_id', 'fee_heads.type']);

        $yearly = [];
        $oneTime = [];
        foreach ($rows as $row) {
            if ($row->type === FeeHeadType::Yearly->value) {
                $yearly["{$row->enrollment_id}:{$row->fee_head_id}"] = true;
            } else {
                $oneTime["{$row->student_id}:{$row->fee_head_id}"] = true;
            }
        }

        return [$yearly, $oneTime];
    }

    private function waiver(?FeeWaiver $waiver, int $amount): int
    {
        if ($waiver === null) {
            return 0;
        }

        $off = $waiver->type === WaiverType::Percent
            ? Money::percentOf($amount, $waiver->value)
            : Money::toPaisa($waiver->value);

        return min($off, $amount);
    }

    /**
     * @param  list<array{fee_head_id: int, amount: int, waiver: int}>  $items
     */
    private function createInvoice(Enrollment $enrollment, array $items, string $number, string $month, CarbonImmutable $first): void
    {
        $subtotal = array_sum(array_column($items, 'amount'));
        $waived = array_sum(array_column($items, 'waiver'));

        $invoice = new Invoice([
            'enrollment_id' => $enrollment->id,
            'invoice_no' => $number,
            'month' => $month,
            'issue_date' => $first,
            'due_date' => $first->setDay(min((int) config('school.fees.due_day'), $first->daysInMonth)),
            'subtotal' => Money::fromPaisa($subtotal),
            'waiver_total' => Money::fromPaisa($waived),
            'total' => Money::fromPaisa($subtotal - $waived),
        ]);
        $invoice->applyPaid(0);
        $invoice->save();

        $invoice->items()->createMany(array_map(fn (array $item) => [
            'fee_head_id' => $item['fee_head_id'],
            'amount' => Money::fromPaisa($item['amount']),
            'waiver_amount' => Money::fromPaisa($item['waiver']),
            'net_amount' => Money::fromPaisa($item['amount'] - $item['waiver']),
        ], $items));
    }
}
