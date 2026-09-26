<?php

namespace App\Models\Concerns;

use App\Models\School;
use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * For every tenant-owned model: queries only see the current school's rows,
 * and new rows get the current school's id.
 *
 * With no current school the scope matches nothing (fail closed). Platform
 * code that really needs every school uses withoutGlobalScope('school').
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope('school', function (Builder $query) {
            $schoolId = app(CurrentSchool::class)->id();

            if ($schoolId === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where($query->qualifyColumn('school_id'), $schoolId);
        });

        static::creating(function (Model $model) {
            $schoolId = app(CurrentSchool::class)->id();

            if ($schoolId !== null) {
                $model->setAttribute('school_id', $schoolId);
            } elseif ($model->getAttribute('school_id') === null) {
                throw new LogicException('Cannot create '.$model::class.' without a current school.');
            }
        });
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
