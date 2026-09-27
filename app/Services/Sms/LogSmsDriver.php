<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Development driver: nothing leaves the server; the message goes to laravel.log
 * (and SmsService records it in sms_logs as usual).
 */
class LogSmsDriver implements SmsDriver
{
    public function send(string $to, string $message): array
    {
        Log::info('SMS (log driver)', ['to' => $to, 'message' => $message]);

        return ['driver' => 'log'];
    }
}
