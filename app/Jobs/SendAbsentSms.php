<?php

namespace App\Jobs;

use App\Enums\AttendanceStatus;
use App\Enums\SmsStatus;
use App\Enums\SmsType;
use App\Models\School;
use App\Models\StudentAttendance;
use App\Services\Sms\SmsService;
use App\Support\CurrentSchool;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Tells the primary guardian their child is absent today (business-rules §4).
 * At most once per attendance record: the row is claimed (sms_sent_at) under a
 * lock before sending, so retries and duplicate dispatches send nothing twice.
 */
class SendAbsentSms implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public int $attendanceId, public int $schoolId) {}

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
        $claimed = DB::transaction(function () use ($school) {
            $attendance = StudentAttendance::query()->lockForUpdate()->find($this->attendanceId);

            if ($attendance === null || $attendance->status !== AttendanceStatus::Absent || $attendance->sms_sent_at !== null || ! $school->absent_sms_enabled) {
                return null;
            }

            $attendance->forceFill(['sms_sent_at' => now()])->save();

            return $attendance;
        });

        if ($claimed === null) {
            return;
        }

        $claimed->load('enrollment.student.guardians');
        $guardian = $claimed->enrollment->student->guardians->firstWhere('pivot.is_primary', true);

        if ($guardian === null) {
            return;
        }

        $log = $sms->send(SmsType::Absent, $guardian->phone, $this->message($school, $claimed));

        // Not sent (e.g. no balance): release the claim so a later save can try again.
        if ($log->status !== SmsStatus::Sent) {
            $claimed->forceFill(['sms_sent_at' => null])->save();
        }
    }

    private function message(School $school, StudentAttendance $attendance): string
    {
        $bangla = $school->default_locale === 'bn';
        $student = $attendance->enrollment->student;
        $digits = fn (string|int $value) => $bangla ? bn_digits($value) : (string) $value;

        return __('sms.absent', [
            'name' => $bangla ? ($student->name_bn ?: $student->name_en) : $student->name_en,
            'roll' => $digits($attendance->enrollment->roll),
            'date' => $digits($attendance->date->format('d/m/Y')),
            'school' => $bangla ? $school->name_bn : $school->name_en,
        ], $school->default_locale);
    }
}
