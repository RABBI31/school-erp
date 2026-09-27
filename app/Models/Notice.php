<?php

namespace App\Models;

use App\Enums\NoticeAudience;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Staff see notices on the dashboard from published_at on. Guardians have no
 * portal yet; their notices can go out as SMS.
 */
class Notice extends Model
{
    use BelongsToSchool;

    protected $fillable = ['title_en', 'title_bn', 'body_en', 'body_bn', 'audience', 'school_class_id', 'published_at'];

    protected $hidden = ['attachment_path'];

    protected function casts(): array
    {
        return [
            'audience' => NoticeAudience::class,
            'published_at' => 'immutable_datetime',
        ];
    }

    /**
     * Published notices meant for staff (audience all or staff).
     *
     * @param  Builder<Notice>  $query
     */
    public function scopeForStaff(Builder $query): void
    {
        $query->where('published_at', '<=', now())->whereIn('audience', [NoticeAudience::All, NoticeAudience::Staff]);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
