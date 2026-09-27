<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAttendance extends Model
{
    use BelongsToSchool;

    protected $fillable = ['staff_id', 'date', 'status', 'in_time', 'out_time'];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date:Y-m-d',
            'status' => AttendanceStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
