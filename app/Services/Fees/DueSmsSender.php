<?php

namespace App\Services\Fees;

use App\Jobs\SendDueSms;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\Cache;

/**
 * Queues due reminders, at most one per student per day (a second click, or a
 * second admin, sends nothing new).
 */
class DueSmsSender
{
    public function __construct(private CurrentSchool $currentSchool) {}

    /**
     * @param  iterable<int>  $enrollmentIds
     * @return int how many were queued
     */
    public function queue(iterable $enrollmentIds): int
    {
        $schoolId = $this->currentSchool->id();
        $today = now()->toDateString();
        $queued = 0;

        foreach ($enrollmentIds as $id) {
            if (Cache::add("due-sms:{$schoolId}:{$id}:{$today}", true, now()->endOfDay())) {
                SendDueSms::dispatch($id, $schoolId);
                $queued++;
            }
        }

        return $queued;
    }
}
