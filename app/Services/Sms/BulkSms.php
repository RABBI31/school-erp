<?php

namespace App\Services\Sms;

use App\Enums\SmsType;
use App\Exceptions\BusinessRuleException;
use App\Jobs\SendCustomSms;
use App\Support\CurrentSchool;

/**
 * Sending one message to many guardians: a preview of the cost, and a refusal
 * up front when the balance cannot cover everyone.
 */
class BulkSms
{
    public function __construct(private CurrentSchool $currentSchool) {}

    /**
     * @param  list<string>  $phones
     * @return array{recipients: int, parts: int, cost: int, balance: int, enough: bool}
     */
    public function preview(array $phones, string $message): array
    {
        $parts = $message === '' ? 0 : SmsParts::count($message);
        $balance = $this->currentSchool->get()->fresh()->sms_balance;
        $cost = $parts * count($phones);

        return ['recipients' => count($phones), 'parts' => $parts, 'cost' => $cost, 'balance' => $balance, 'enough' => $cost <= $balance];
    }

    /**
     * @param  list<string>  $phones
     * @return int how many were queued
     */
    public function send(array $phones, string $message, SmsType $type = SmsType::Custom): int
    {
        $preview = $this->preview($phones, $message);

        if ($preview['recipients'] === 0) {
            throw new BusinessRuleException(__('messages.sms_no_recipients'));
        }

        if (! $preview['enough']) {
            throw new BusinessRuleException(__('messages.sms_balance_short', ['cost' => $preview['cost'], 'balance' => $preview['balance']]));
        }

        foreach ($phones as $phone) {
            SendCustomSms::dispatch($phone, $message, $this->currentSchool->id(), $type->value);
        }

        return count($phones);
    }
}
