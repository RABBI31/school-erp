<?php

namespace App\Services\Sms;

use App\Enums\NoticeAudience;
use App\Enums\SmsType;
use App\Models\Notice;
use App\Support\CurrentAcademicYear;
use App\Support\CurrentSchool;

/**
 * A notice sent to its guardians by SMS, in the school's language:
 * "title: body — school". Staff-only notices are never texted.
 */
class NoticeSms
{
    public function __construct(
        private SmsAudience $audience,
        private BulkSms $bulk,
        private CurrentSchool $currentSchool,
        private CurrentAcademicYear $currentAcademicYear,
    ) {}

    public function message(Notice $notice): string
    {
        $school = $this->currentSchool->get();
        $bn = $school->default_locale === 'bn';
        $title = $bn ? $notice->title_bn : $notice->title_en;
        $body = trim((string) ($bn ? ($notice->body_bn ?: $notice->body_en) : ($notice->body_en ?: $notice->body_bn)));

        return __($body === '' ? 'sms.notice_title_only' : 'sms.notice', [
            'title' => $title,
            'body' => $body,
            'school' => $bn ? $school->name_bn : $school->name_en,
        ], $school->default_locale);
    }

    /**
     * @return list<string>
     */
    public function phones(Notice $notice): array
    {
        if (! $notice->audience->reachesGuardians()) {
            return [];
        }

        return $this->audience->phones(
            $this->currentAcademicYear->id(),
            $notice->audience === NoticeAudience::SchoolClass ? $notice->school_class_id : null,
        );
    }

    public function send(Notice $notice): int
    {
        return $notice->audience->reachesGuardians()
            ? $this->bulk->send($this->phones($notice), $this->message($notice), SmsType::Notice)
            : 0;
    }
}
