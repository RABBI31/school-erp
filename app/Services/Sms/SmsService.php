<?php

namespace App\Services\Sms;

use App\Enums\SmsStatus;
use App\Enums\SmsType;
use App\Models\School;
use App\Models\SmsLog;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sends an SMS for the current school, charging its SMS balance per part
 * (business-rules §5). Never sends without enough balance.
 */
class SmsService
{
    public function __construct(private SmsDriver $driver, private CurrentSchool $currentSchool) {}

    public function send(SmsType $type, string $to, string $message): SmsLog
    {
        $parts = SmsParts::count($message);

        // Short transaction: reserve the balance and record the attempt.
        $log = DB::transaction(function () use ($type, $to, $message, $parts) {
            $school = School::query()->lockForUpdate()->findOrFail($this->currentSchool->id());
            $enough = $school->sms_balance >= $parts;

            if ($enough) {
                $school->decrement('sms_balance', $parts);
            }

            return SmsLog::create([
                'to_phone' => $to,
                'message' => $message,
                'type' => $type,
                'parts' => $parts,
                'status' => $enough ? SmsStatus::Queued : SmsStatus::Failed,
                'error' => $enough ? null : __('messages.sms_no_balance', ['parts' => $parts, 'balance' => $school->sms_balance]),
            ]);
        });

        if ($log->status === SmsStatus::Failed) {
            return $log;
        }

        // The provider call happens outside the transaction.
        try {
            $response = $this->driver->send($to, $message);
            $log->update(['status' => SmsStatus::Sent, 'provider_response' => $response, 'sent_at' => now()]);
        } catch (Throwable $e) {
            DB::transaction(function () use ($log, $parts, $e) {
                School::query()->whereKey($this->currentSchool->id())->increment('sms_balance', $parts);
                $log->update(['status' => SmsStatus::Failed, 'error' => mb_substr($e->getMessage(), 0, 250)]);
            });
        }

        return $log;
    }
}
