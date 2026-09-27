<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAttendance extends Model
{
    use BelongsToSchool;

    protected $fillable = ['enrollment_id', 'date', 'status', 'taken_by'];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date:Y-m-d',
            'status' => AttendanceStatus::class,
            'sms_sent_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
