<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarkAudit extends Model
{
    use BelongsToSchool;

    public $timestamps = false;

    protected $fillable = ['mark_id', 'changed_by', 'old_values', 'new_values', 'changed_at'];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'changed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Mark, $this>
     */
    public function mark(): BelongsTo
    {
        return $this->belongsTo(Mark::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
