<?php

namespace App\Jobs;

use App\Enums\SmsStatus;
use App\Enums\SmsType;
use App\Models\ExamResult;
use App\Models\School;
use App\Services\Sms\SmsService;
use App\Support\CurrentSchool;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Tells the primary guardian the student's GPA (business-rules §5). At most
 * once per result: the row is claimed (sms_sent_at) under a lock before sending.
 */
class SendResultSms implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public int $examResultId, public int $schoolId) {}

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
        $claimed = DB::transaction(function () {
            $result = ExamResult::query()->lockForUpdate()->find($this->examResultId);

            if ($result === null || $result->sms_sent_at !== null) {
                return null;
            }

            $result->forceFill(['sms_sent_at' => now()])->save();

            return $result;
        });

        if ($claimed === null) {
            return;
        }

        $claimed->load('exam', 'enrollment.student.guardians');
        $guardian = $claimed->enrollment->student->guardians->firstWhere('pivot.is_primary', true);

        if ($guardian === null || ! $claimed->exam->isPublished()) {
            return;
        }

        $bangla = $school->default_locale === 'bn';
        $student = $claimed->enrollment->student;

        $log = $sms->send(SmsType::Result, $guardian->phone, __('sms.result', [
            'name' => $bangla ? ($student->name_bn ?: $student->name_en) : $student->name_en,
            'exam' => $bangla ? $claimed->exam->name_bn : $claimed->exam->name_en,
            'gpa' => $bangla ? bn_digits($claimed->gpa) : $claimed->gpa,
            'school' => $bangla ? $school->name_bn : $school->name_en,
        ], $school->default_locale));

        // Not sent (e.g. no balance): release the claim so it can be sent again later.
        if ($log->status !== SmsStatus::Sent) {
            $claimed->forceFill(['sms_sent_at' => null])->save();
        }
    }
}
