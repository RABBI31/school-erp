<?php

namespace App\Services\Sms;

interface SmsDriver
{
    /**
     * Send one message. Throw on failure.
     *
     * @return array<string, mixed> the provider's response, stored on the log
     */
    public function send(string $to, string $message): array;
}
