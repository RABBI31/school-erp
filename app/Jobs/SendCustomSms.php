<?php

namespace App\Jobs;

use App\Enums\SmsType;
use App\Models\School;
use App\Services\Sms\SmsService;
use App\Support\CurrentSchool;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One SMS written by the school (a custom message or a notice) to one phone.
 */
class SendCustomSms implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public string $phone, public string $message, public int $schoolId, public string $type = 'custom') {}

    public function handle(SmsService $sms, CurrentSchool $currentSchool): void
    {
        $previous = $currentSchool->get();
        $currentSchool->set(School::query()->findOrFail($this->schoolId)); // a queue worker has no request, so no school yet

        try {
            $sms->send(SmsType::from($this->type), $this->phone, $this->message);
        } finally {
            $currentSchool->set($previous);
        }
    }
}
