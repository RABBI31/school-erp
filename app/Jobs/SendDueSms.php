<?php

namespace App\Jobs;

use App\Enums\SmsType;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\School;
use App\Services\Sms\SmsService;
use App\Support\CurrentSchool;
use App\Support\Money;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reminds the primary guardian of what a student owes (business-rules §5,
 * "Due" template). The amount is read when the job runs, so a payment made in
 * the meantime is taken into account. DueSmsSender allows one per student per day.
 */
class SendDueSms implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public int $enrollmentId, public int $schoolId) {}

    public function handle(SmsService $sms, CurrentSchool $currentSchool): void
    {
        $previous = $currentSchool->get();
        $school = School::query()->findOrFail($this->schoolId);
        $currentSchool->set($school); // a queue worker has no request, so no school yet

        try {
            $this->send($school, $sms);
        } finally {
            $currentSchool->set($previous);
        }
    }

    private function send(School $school, SmsService $sms): void
    {
        $enrollment = Enrollment::query()->with('student.guardians')->find($this->enrollmentId);
        $guardian = $enrollment?->student?->guardians->firstWhere('pivot.is_primary', true);
        $invoices = Invoice::query()->where('enrollment_id', $this->enrollmentId)->outstanding()->get();
        $due = $invoices->sum(fn (Invoice $invoice) => $invoice->duePaisa());

        if ($guardian === null || $due <= 0) {
            return;
        }

        $bangla = $school->default_locale === 'bn';
        $student = $enrollment->student;
        $dueDate = $invoices->max('due_date')->format('d/m/Y');

        $sms->send(SmsType::Due, $guardian->phone, __('sms.due', [
            'name' => $bangla ? ($student->name_bn ?: $student->name_en) : $student->name_en,
            'amount' => Money::format(Money::fromPaisa($due), $bangla),
            'due_date' => $bangla ? bn_digits($dueDate) : $dueDate,
            'school' => $bangla ? $school->name_bn : $school->name_en,
        ], $school->default_locale));
    }
}
