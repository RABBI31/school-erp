<?php

namespace App\Services\Dashboard;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Section;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StudentAttendance;
use App\Services\Attendance\AttendanceCalendar;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The numbers on the dashboard (planning step 9), for the academic year being
 * viewed. Amounts are decimal strings.
 */
class DashboardStats
{
    public function __construct(private AttendanceCalendar $calendar) {}

    /**
     * Today's student attendance, limited to $sectionIds when given (a teacher's own).
     *
     * @param  list<int>|null  $sectionIds
     * @return array<string, mixed>
     */
    public function attendance(?int $yearId, CarbonImmutable $today, ?array $sectionIds = null): array
    {
        $sections = Section::query()->when($sectionIds !== null, fn (Builder $q) => $q->whereIn('id', $sectionIds));

        if ($this->calendar->isWeekend($today)) {
            return ['weekend' => true, 'sections' => $sections->count()];
        }

        $records = StudentAttendance::query()
            ->whereDate('date', $today)
            ->whereHas('enrollment', fn (Builder $q) => $q
                ->where('academic_year_id', $yearId)
                ->where('status', EnrollmentStatus::Active)
                ->when($sectionIds !== null, fn (Builder $q) => $q->whereIn('section_id', $sectionIds)))
            ->with('enrollment:id,section_id')
            ->get(['id', 'enrollment_id', 'status']);

        $count = fn (AttendanceStatus $status) => $records->where('status', $status)->count();
        $present = $count(AttendanceStatus::Present);
        $late = $count(AttendanceStatus::Late);

        return [
            'weekend' => false,
            'present' => $present,
            'late' => $late,
            'absent' => $count(AttendanceStatus::Absent),
            'leave' => $count(AttendanceStatus::Leave),
            'recorded' => $records->count(),
            'percent' => $records->isEmpty() ? null : round(($present + $late) / $records->count() * 100, 1),
            'sections' => $sections->count(),
            'sections_taken' => $records->pluck('enrollment.section_id')->unique()->count(),
        ];
    }

    /**
     * @return array{present: int, total: int}
     */
    public function staff(CarbonImmutable $today): array
    {
        return [
            'present' => StaffAttendance::query()->whereDate('date', $today)->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::Late])->count(),
            'total' => Staff::query()->where('status', 'active')->count(),
        ];
    }

    /**
     * This month's collection against what this month's invoices billed, and
     * everything still owed in the year.
     *
     * @return array{collected: string, billed: string, dues: string}
     */
    public function fees(?int $yearId, CarbonImmutable $today): array
    {
        $collected = Payment::query()->whereBetween('paid_at', [$today->startOfMonth(), $today->endOfMonth()])->sum('amount');
        $billed = Invoice::query()->where('month', $today->format('Y-m'))->where('status', '!=', InvoiceStatus::Cancelled)->sum('total');
        $dues = Invoice::query()->outstanding()->whereHas('enrollment', fn (Builder $q) => $q->where('academic_year_id', $yearId))
            ->selectRaw('COALESCE(SUM(total - paid_total), 0) AS due')->value('due');

        return [
            'collected' => Money::fromPaisa(Money::toPaisa((string) $collected)),
            'billed' => Money::fromPaisa(Money::toPaisa((string) $billed)),
            'dues' => Money::fromPaisa(Money::toPaisa((string) $dues)),
        ];
    }

    /**
     * The latest published exam of the year.
     *
     * @return array<string, mixed>|null
     */
    public function exam(?int $yearId): ?array
    {
        $exam = Exam::query()->where('academic_year_id', $yearId)->whereNotNull('published_at')
            ->latest('published_at')->first(['id', 'name_en', 'name_bn', 'published_at', 'status']);

        if ($exam === null || ! $exam->isPublished()) {
            return null;
        }

        $results = ExamResult::query()->where('exam_id', $exam->id)->get(['gpa', 'fail_count']);
        $passed = $results->filter(fn (ExamResult $r) => $r->fail_count === 0 && (float) $r->gpa > 0)->count();

        return [
            'id' => $exam->id,
            'name_en' => $exam->name_en,
            'name_bn' => $exam->name_bn,
            'students' => $results->count(),
            'passed' => $passed,
            'pass_rate' => $results->isEmpty() ? 0 : round($passed / $results->count() * 100, 1),
            'gpa5' => $results->where('gpa', '5.00')->count(),
        ];
    }
}
