<?php

namespace App\Models;

use App\Enums\SmsStatus;
use App\Enums\SmsType;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    use BelongsToSchool;

    protected $fillable = ['to_phone', 'message', 'type', 'status', 'parts', 'provider_response', 'error', 'sent_at'];

    protected function casts(): array
    {
        return [
            'type' => SmsType::class,
            'status' => SmsStatus::class,
            'parts' => 'integer',
            'provider_response' => 'array',
            'sent_at' => 'immutable_datetime',
        ];
    }
}
